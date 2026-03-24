#!/usr/bin/env python
"""Minimal static server for ui/dist."""

import argparse
import os
import sys
from http.server import SimpleHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path
from urllib.parse import unquote, urlparse

DEFAULT_HOST = "0.0.0.0"
DEFAULT_PORT = 9999
PROJECT_ROOT = Path(__file__).resolve().parents[1]
DEFAULT_DIST_DIR = PROJECT_ROOT / "ui" / "dist"


def _build_handler(directory):
    class DistHandler(SimpleHTTPRequestHandler):
        def __init__(self, *args, **kwargs):
            super().__init__(*args, directory=str(directory), **kwargs)

        def _maybe_rewrite_to_index(self):
            url_path = unquote(urlparse(self.path).path)
            if not url_path or url_path == "/":
                return
            if "." in Path(url_path).name:
                return

            fs_path = self.translate_path(url_path)
            if not os.path.exists(fs_path):
                self.path = "/index.html"

        def do_GET(self):
            self._maybe_rewrite_to_index()
            super().do_GET()

        def do_HEAD(self):
            self._maybe_rewrite_to_index()
            super().do_HEAD()

    return DistHandler


def parse_args():
    parser = argparse.ArgumentParser(
        description="Serve static files from ui/dist on port 9999 by default."
    )
    parser.add_argument("--host", default=DEFAULT_HOST, help="Host to bind to.")
    parser.add_argument("--port", type=int, default=DEFAULT_PORT, help="Port to use.")
    parser.add_argument(
        "--dist",
        default=str(DEFAULT_DIST_DIR),
        help="Path to static dist directory.",
    )
    return parser.parse_args()


def main():
    args = parse_args()
    dist_dir = Path(args.dist).resolve()

    if not dist_dir.exists() or not dist_dir.is_dir():
        print("Dist directory not found: {0}".format(dist_dir), file=sys.stderr)
        print("Build UI first, for example: npm --prefix ui run build", file=sys.stderr)
        return 1

    handler = _build_handler(dist_dir)
    server = ThreadingHTTPServer((args.host, args.port), handler)

    print("Serving: {0}".format(dist_dir))
    print("URL: http://{0}:{1}".format(args.host, args.port))

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
