#!/usr/bin/env python3
"""Static server that sends charset=utf-8 for HTML.

`python3 -m http.server` sends `text/html` with no charset, so browsers decode
as latin-1 and every arrow, em-dash, and ellipsis in the card renders as
mojibake — the page still looks finished, which is what makes it dangerous.

Usage:  python3 serve.py [port]        # default 8792, binds 127.0.0.1 only
Serves the current working directory.
"""

import http.server
import sys


class UTF8Handler(http.server.SimpleHTTPRequestHandler):
    def guess_type(self, path):
        ctype = super().guess_type(path)
        if isinstance(ctype, str) and ctype.startswith("text/html"):
            return "text/html; charset=utf-8"
        return ctype

    def log_message(self, fmt, *args):
        pass  # quiet: the render loop doesn't need request logs


if __name__ == "__main__":
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8792
    print(f"serving {sys.argv[0]}'s directory on http://127.0.0.1:{port}", flush=True)
    http.server.test(HandlerClass=UTF8Handler, port=port, bind="127.0.0.1")
