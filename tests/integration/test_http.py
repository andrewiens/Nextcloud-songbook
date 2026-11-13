#!/usr/bin/env python3
"""Live HTTP tests against the disposable local Nextcloud 35 instance.

Creates and removes two temporary non-admin accounts using the official
provisioning API. Refuses remote targets. No credentials are printed or saved.
Run: python3 tests/integration/test_http.py --env-file .env
"""

import argparse
import base64
import concurrent.futures
import html
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import unittest
import urllib.error
import urllib.parse
import urllib.request


API_PATH = "/ocs/v2.php/apps/songbook/api/v1/status"
CONFIG = {}


def request(path, *, credentials=None, method="GET", data=None, headers=None, opener=None):
    request_headers = {"Accept": "application/json"}
    request_headers.update(headers or {})
    if credentials:
        encoded = base64.b64encode(":".join(credentials).encode()).decode()
        request_headers["Authorization"] = "Basic " + encoded
        request_headers["OCS-APIRequest"] = "true"
    body = urllib.parse.urlencode(data).encode() if data is not None else None
    req = urllib.request.Request(CONFIG["base_url"] + path, data=body,
                                 headers=request_headers, method=method)
    try:
        response = (opener or urllib.request.build_opener()).open(req, timeout=30)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, response.read(), response.headers


def csrf_token(body):
    match = re.search(r'data-requesttoken="([^"]+)"', body.decode())
    if not match:
        raise AssertionError("Nextcloud HTML has no request token")
    return html.unescape(match.group(1))


class SongbookHttpTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        status, body, _ = request("/status.php")
        if status != 200 or json.loads(body).get("version", "").split(".")[0] != "35":
            raise RuntimeError("Tests require an installed Nextcloud 35 instance")
        cls.admin = (CONFIG["NEXTCLOUD_ADMIN_USER"], CONFIG["NEXTCLOUD_ADMIN_PASSWORD"])
        cls.users = []
        cls.addClassCleanup(cls.remove_test_users)
        for suffix in ("a", "b"):
            credentials = ("songbook-test-" + secrets.token_hex(5) + suffix,
                           secrets.token_urlsafe(32))
            status, body, _ = request("/ocs/v2.php/cloud/users", credentials=cls.admin,
                                      method="POST", data={"userid": credentials[0],
                                                           "password": credentials[1],
                                                           "language": "de"})
            if status != 200 or json.loads(body)["ocs"]["meta"]["status"] != "ok":
                raise RuntimeError("Cannot create temporary local test user")
            cls.users.append(credentials)

    @classmethod
    def remove_test_users(cls):
        for user, _ in cls.users:
            status, _, _ = request("/ocs/v2.php/cloud/users/" + urllib.parse.quote(user),
                                   credentials=cls.admin, method="DELETE")
            if status != 200:
                raise RuntimeError("Temporary test account cleanup failed: " + user)

    def assert_status_payload(self, response):
        status, body, headers = response
        self.assertEqual(status, 200)
        self.assertIn("application/json", headers.get("Content-Type", ""))
        payload = json.loads(body)
        self.assertEqual(payload["ocs"]["meta"]["status"], "ok")
        data = payload["ocs"]["data"]
        self.assertEqual(data["app"], "songbook")
        self.assertEqual(data["apiVersion"], 1)
        self.assertTrue(data["version"])
        self.assertEqual(data["capabilities"], {"songLibrary": True, "setlists": False})

    def test_anonymous_request_is_rejected(self):
        status, _, _ = request(API_PATH, headers={"OCS-APIRequest": "true"})
        self.assertIn(status, (401, 403))

    def test_both_band_members_can_use_api_concurrently(self):
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            results = list(pool.map(lambda user: request(API_PATH, credentials=user), self.users))
        for result in results:
            self.assert_status_payload(result)

    def test_api_does_not_accept_writes(self):
        status, _, _ = request(API_PATH, credentials=self.users[0], method="POST", data={})
        self.assertIn(status, (404, 405))

    def test_page_requires_login(self):
        status, body, _ = request("/index.php/apps/songbook/", headers={"Accept": "text/html"})
        self.assertEqual(status, 200)
        self.assertNotIn(b'id="songbook-app"', body)

    def test_development_credentials_are_not_web_accessible(self):
        for filename in (".env", ".git/config"):
            status, _, _ = request("/custom_apps/songbook/" + filename)
            self.assertIn(status, (403, 404), "Development files must be inaccessible over HTTP")

    def test_browser_session_page_assets_and_csrf(self):
        opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        _, login_page, _ = request("/login", opener=opener, headers={"Accept": "text/html"})
        request("/login", opener=opener, method="POST", data={
            "user": self.users[0][0], "password": self.users[0][1],
            "requesttoken": csrf_token(login_page), "timezone": "Europe/Berlin",
        }, headers={"Accept": "text/html", "Origin": CONFIG["base_url"]})
        status, page, _ = request("/index.php/apps/songbook/", opener=opener,
                                  headers={"Accept": "text/html"})
        self.assertEqual(status, 200)
        self.assertTrue(b'id="songbook-app"' in page, "Authenticated app page was not returned")
        for kind in ("js", "css"):
            asset = re.search(rb'(?:src|href)="([^"]*songbook-main\.' + kind.encode() + rb'[^\"]*)"', page)
            self.assertIsNotNone(asset, "Built " + kind + " asset is not included")
            asset_status, content, _ = request(html.unescape(asset.group(1).decode()), opener=opener)
            self.assertEqual(asset_status, 200)
            self.assertGreater(len(content), 50)
        self.assert_status_payload(request(API_PATH, opener=opener,
                                           headers={"requesttoken": csrf_token(page)}))
        status, _, _ = request(API_PATH, opener=opener)
        self.assertEqual(status, 412, "Nextcloud OCS must reject a missing CSRF token with 412")


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
