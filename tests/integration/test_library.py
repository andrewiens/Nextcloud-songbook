#!/usr/bin/env python3
"""Phase 2 integration tests on the disposable localhost Nextcloud 35.

Creates four temporary accounts, an actual shared folder and ChordPro files.
Accounts (and therefore their files/shares) are removed even on test failure.
Library metadata IDs are reported for optional administrator cleanup because
Phase 2 intentionally has no library deletion endpoint.

Run: python3 tests/integration/test_library.py --env-file .env
"""
# SPDX-License-Identifier: AGPL-3.0-or-later

import argparse
import base64
import concurrent.futures
import json
import os
from pathlib import Path
import secrets
import unittest
import urllib.error
import urllib.parse
import urllib.request

from test_http import CONFIG, request


API = "/ocs/v2.php/apps/songbook/api/v1"
SHARES = "/ocs/v2.php/apps/files_sharing/api/v1/shares"
MAX_BYTES = 256 * 1024


def raw_request(path, *, credentials, method="GET", body=None, headers=None):
    """Bytes for WebDAV and JSON; never log credentials or raw responses."""
    request_headers = {
        "Accept": "application/json",
        "OCS-APIRequest": "true",
        "Authorization": "Basic " + base64.b64encode(":".join(credentials).encode()).decode(),
    }
    request_headers.update(headers or {})
    req = urllib.request.Request(CONFIG["base_url"] + path, data=body,
                                 headers=request_headers, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=45)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read(), response.headers


def api(path, credentials, *, method="GET", data=None):
    return raw_request(API + path, credentials=credentials, method=method,
                       body=json.dumps(data).encode() if data is not None else None,
                       headers={"Content-Type": "application/json"})


def success(response, allowed=(200, 201)):
    status, body, _ = response
    if status not in allowed:
        raise AssertionError("Expected successful HTTP response, got " + str(status))
    payload = json.loads(body)
    if payload.get("ocs", {}).get("meta", {}).get("status") != "ok":
        raise AssertionError("Expected successful OCS response")
    return payload["ocs"]["data"]


def dav_path(credentials, path):
    return "/remote.php/dav/files/" + urllib.parse.quote(credentials[0], safe="") + "/" + urllib.parse.quote(path, safe="/")


class LibraryHttpTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        status, body, _ = request("/status.php")
        if status != 200 or json.loads(body).get("version", "").split(".")[0] != "35":
            raise RuntimeError("Tests require a locally installed Nextcloud 35")
        cls.admin = (CONFIG["NEXTCLOUD_ADMIN_USER"], CONFIG["NEXTCLOUD_ADMIN_PASSWORD"])
        cls.users = []
        cls.library_ids = []
        cls.addClassCleanup(cls.cleanup_accounts)
        for role in ("owner", "editor", "reader", "outsider"):
            credentials = ("sb-phase2-" + secrets.token_hex(5) + "-" + role,
                           secrets.token_urlsafe(32))
            success(request("/ocs/v2.php/cloud/users", credentials=cls.admin,
                            method="POST", data={"userid": credentials[0],
                                                  "password": credentials[1]}))
            cls.users.append(credentials)
            setattr(cls, role, credentials)
        cls.folder_name = "Songbook-test-" + secrets.token_hex(6)
        status, _, _ = raw_request(dav_path(cls.owner, cls.folder_name),
                                   credentials=cls.owner, method="MKCOL")
        if status != 201:
            raise AssertionError("Cannot create WebDAV test folder: " + str(status))
        for credentials, permissions in ((cls.editor, 31), (cls.reader, 1)):
            success(request(SHARES, credentials=cls.owner, method="POST", data={
                "path": "/" + cls.folder_name, "shareType": 0,
                "shareWith": credentials[0], "permissions": permissions,
            }))
        folders = success(api("/folders?folderId=0", cls.owner))["folders"]
        folder = next((item for item in folders if item["name"] == cls.folder_name), None)
        if folder is None:
            raise AssertionError("New folder missing from folder browser")
        cls.folder_id = folder["id"]
        cls.library = success(api("/libraries", cls.owner, method="POST", data={
            "name": "Integration test band", "folderId": cls.folder_id,
        }))["library"]
        cls.library_ids.append(cls.library["id"])

    @classmethod
    def cleanup_accounts(cls):
        errors = []
        for credentials in cls.users:
            status, _, _ = request("/ocs/v2.php/cloud/users/" + urllib.parse.quote(credentials[0]),
                                    credentials=cls.admin, method="DELETE")
            if status != 200:
                errors.append(credentials[0])
        if cls.library_ids:
            print("Temporary library metadata IDs (files/accounts removed): "
                  + ", ".join(str(value) for value in cls.library_ids))
        if errors:
            raise AssertionError("Cannot remove temporary accounts: " + ", ".join(errors))

    def source(self, title):
        return "{title: " + title + "}\r\n{artist: Test Band}\r\n{key: D}\r\n{future_directive: unchanged}\r\n\r\nA [D]test line.\r\n"

    def import_song(self, filename, content=None, credentials=None):
        return success(api("/songs/import", credentials or self.owner, method="POST", data={
            "libraryId": self.library["id"], "filename": filename,
            "content": content if content is not None else self.source(filename),
        }))["song"]

    def songs(self, credentials=None, search="", sort="title"):
        query = urllib.parse.urlencode({"libraryId": self.library["id"], "search": search, "sort": sort})
        return success(api("/songs?" + query, credentials or self.owner))

    def test_shared_library_visibility_and_same_song_reference(self):
        song = self.import_song("shared-reference.cho")
        for credentials, can_import in ((self.owner, True), (self.editor, True), (self.reader, False)):
            libraries = success(api("/libraries", credentials))["libraries"]
            library = next(item for item in libraries if item["id"] == self.library["id"])
            self.assertEqual(library["folderId"], self.folder_id)
            self.assertEqual(library["canImport"], can_import)
            current = next(item for item in self.songs(credentials)["songs"] if item["id"] == song["id"])
            self.assertEqual(current["fileId"], song["fileId"])
            self.assertEqual(success(api("/songs/" + str(song["id"]), credentials))["song"]["content"],
                             self.source("shared-reference.cho"))

    def test_unauthorized_ids_and_read_only_import_are_rejected(self):
        song = self.import_song("permission-check.cho")
        libraries = success(api("/libraries", self.outsider))["libraries"]
        self.assertFalse(any(item["id"] == self.library["id"] for item in libraries))
        for path in ("/songs?libraryId=" + str(self.library["id"]),
                     "/songs/" + str(song["id"]),
                     "/folders?folderId=" + str(self.folder_id)):
            self.assertIn(api(path, self.outsider)[0], (403, 404))
        self.assertEqual(api("/songs/import", self.reader, method="POST", data={
            "libraryId": self.library["id"], "filename": "forbidden.cho", "content": self.source("Forbidden"),
        })[0], 403)
        self.assertIn(api("/libraries", self.reader, method="POST", data={
            "name": "Read-only attempt", "folderId": self.folder_id,
        })[0], (403, 404))

    def test_import_preserves_original_and_does_not_overwrite(self):
        original = self.source("Original äöü")
        song = self.import_song("preserved.chordpro", original, self.editor)
        status, content, _ = raw_request(dav_path(self.owner, self.folder_name + "/preserved.chordpro"), credentials=self.owner)
        self.assertEqual(status, 200)
        self.assertEqual(content, original.encode())
        duplicate = api("/songs/import", self.owner, method="POST", data={
            "libraryId": self.library["id"], "filename": "preserved.chordpro", "content": self.source("Overwrite"),
        })
        self.assertEqual(duplicate[0], 409)
        fetched = success(api("/songs/" + str(song["id"]), self.reader))["song"]
        self.assertEqual(fetched["content"], original)

    def test_concurrent_import_never_overwrites_another_import(self):
        def submit(index):
            return api("/songs/import", (self.owner, self.editor)[index], method="POST", data={
                "libraryId": self.library["id"], "filename": "concurrent.cho", "content": self.source("Winner " + str(index)),
            })
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            responses = list(pool.map(submit, (0, 1)))
        winners = [index for index, response in enumerate(responses) if response[0] in (200, 201)]
        self.assertEqual(len(winners), 1, "Exactly one import may create the file")
        loser = 1 - winners[0]
        self.assertIn(responses[loser][0], (409, 423), "Concurrent duplicate must report conflict/lock")
        _, content, _ = raw_request(dav_path(self.owner, self.folder_name + "/concurrent.cho"), credentials=self.owner)
        self.assertEqual(content.decode(), self.source("Winner " + str(winners[0])))

    def test_external_update_rename_and_delete_reconcile(self):
        song = self.import_song("external-before.pro", self.source("Before external edit"))
        source_path = dav_path(self.owner, self.folder_name + "/external-before.pro")
        status, _, _ = raw_request(source_path, credentials=self.owner, method="PUT",
                                   body=self.source("After external edit").encode(), headers={"Content-Type": "text/plain"})
        self.assertIn(status, (201, 204))
        changed = next(item for item in self.songs(self.reader)["songs"] if item["id"] == song["id"])
        self.assertEqual(changed["title"], "After external edit")
        self.assertNotEqual(changed["version"], song["version"])
        destination = dav_path(self.owner, self.folder_name + "/external-after.pro")
        status, _, _ = raw_request(source_path, credentials=self.owner, method="MOVE", headers={
            "Destination": CONFIG["base_url"] + destination, "Overwrite": "F",
        })
        self.assertIn(status, (201, 204))
        renamed = next(item for item in self.songs(self.reader)["songs"] if item["id"] == song["id"])
        self.assertEqual(renamed["filename"], "external-after.pro")
        self.assertEqual(renamed["fileId"], song["fileId"])
        self.assertIn(raw_request(destination, credentials=self.owner, method="DELETE")[0], (200, 204))
        self.assertFalse(any(item["id"] == song["id"] for item in self.songs()["songs"]))
        self.assertEqual(api("/songs/" + str(song["id"]), self.owner)[0], 404)

    def test_invalid_imports_are_rejected(self):
        for filename in ("../escape.cho", "folder/escape.cho", "folder\\escape.cho", "unsupported.txt", ".cho"):
            with self.subTest(filename=filename):
                response = api("/songs/import", self.owner, method="POST", data={
                    "libraryId": self.library["id"], "filename": filename, "content": self.source("Invalid"),
                })
                self.assertIn(response[0], (400, 422))
        for content, codes in (("bad\0content", (400, 422)), ("x" * (MAX_BYTES + 1), (413,))):
            self.assertIn(api("/songs/import", self.owner, method="POST", data={
                "libraryId": self.library["id"], "filename": "invalid-content.cho", "content": content,
            })[0], codes)

    def test_invalid_external_encoding_is_skipped_with_warning(self):
        path = dav_path(self.owner, self.folder_name + "/invalid-encoding.cho")
        self.assertIn(raw_request(path, credentials=self.owner, method="PUT", body=b"{title: Invalid}\n\xff\xfe")[0], (201, 204))
        try:
            result = self.songs(self.reader)
            self.assertFalse(any(item["filename"] == "invalid-encoding.cho" for item in result["songs"]))
            self.assertTrue(result["warnings"], "Invalid input must be surfaced as a warning")
        finally:
            raw_request(path, credentials=self.owner, method="DELETE")

    def test_search_metadata_and_literal_percent(self):
        matching = self.import_song("search-percent.cho", self.source("100% Match"))
        self.import_song("search-ordinary.cho", self.source("Ordinary title"))
        results = self.songs(search="100% Match")["songs"]
        self.assertEqual([item["id"] for item in results], [matching["id"]])
        results = self.songs(search="%")["songs"]
        self.assertEqual([item["id"] for item in results], [matching["id"]])
        results = self.songs(search="Test Band", sort="artist")["songs"]
        self.assertTrue(any(item["id"] == matching["id"] for item in results))
        results = self.songs(search="D", sort="key")["songs"]
        self.assertTrue(any(item["id"] == matching["id"] for item in results))

    def test_reconnecting_same_folder_uses_existing_library(self):
        second = success(api("/libraries", self.editor, method="POST", data={
            "name": "Another view of same library", "folderId": self.folder_id,
        }))["library"]
        self.assertEqual(second["id"], self.library["id"])
        synced = success(api("/libraries/" + str(self.library["id"]) + "/sync", self.owner, method="POST", data={}))
        self.assertIn("songs", synced)

    def test_revoked_share_denies_previously_visible_ids(self):
        song = self.import_song("revoked-share.cho")
        shared = success(request(SHARES, credentials=self.owner, method="POST", data={
            "path": "/" + self.folder_name, "shareType": 0,
            "shareWith": self.outsider[0], "permissions": 1,
        }))
        try:
            self.assertEqual(success(api("/songs/" + str(song["id"]), self.outsider))["song"]["id"], song["id"])
        finally:
            success(request(SHARES + "/" + str(shared["id"]), credentials=self.owner, method="DELETE"))
        self.assertIn(api("/songs/" + str(song["id"]), self.outsider)[0], (403, 404))
        self.assertFalse(any(item["id"] == self.library["id"] for item in success(api("/libraries", self.outsider))["libraries"]))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--env-file", type=Path, default=Path(".env"))
    args = parser.parse_args()
    if args.env_file.is_file():
        for line in args.env_file.read_text().splitlines():
            if line and not line.startswith("#") and "=" in line:
                key, value = line.split("=", 1)
                CONFIG[key] = value.strip().strip("\"'")
    CONFIG.update(os.environ)
    CONFIG["base_url"] = CONFIG.get("SONGBOOK_TEST_URL", "http://127.0.0.1:" + CONFIG.get("SONGBOOK_PORT", "8081")).rstrip("/")
    target = urllib.parse.urlparse(CONFIG["base_url"])
    if target.hostname not in ("127.0.0.1", "localhost", "::1") or target.scheme not in ("http", "https"):
        parser.error("Refusing non-local test target")
    if not CONFIG.get("NEXTCLOUD_ADMIN_USER") or not CONFIG.get("NEXTCLOUD_ADMIN_PASSWORD"):
        parser.error("Provide local NEXTCLOUD_ADMIN_USER and NEXTCLOUD_ADMIN_PASSWORD in --env-file")
    unittest.main(argv=[__file__], verbosity=2)
