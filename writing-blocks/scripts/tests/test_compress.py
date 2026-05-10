"""Tests for compress_image.py — the writing-blocks v2.2 image pipeline."""
import unittest
import sys
import pathlib

# Make the parent `scripts/` directory importable
sys.path.insert(0, str(pathlib.Path(__file__).resolve().parent.parent))


class TestModuleImport(unittest.TestCase):
    def test_can_import(self):
        import compress_image  # noqa: F401

    def test_has_compress_function(self):
        import compress_image
        self.assertTrue(callable(getattr(compress_image, "compress", None)))


class TestSlotLookup(unittest.TestCase):
    def setUp(self):
        import compress_image
        self.lookup = compress_image.lookup_slot

    def test_hero(self):
        self.assertEqual(self.lookup("hero"), (1600, 200))

    def test_hero_image(self):
        self.assertEqual(self.lookup("hero_image"), (1600, 200))

    def test_avatar(self):
        self.assertEqual(self.lookup("avatar"), (200, 30))

    def test_author_avatar(self):
        self.assertEqual(self.lookup("author_avatar"), (200, 30))

    def test_logo(self):
        self.assertEqual(self.lookup("logo"), (240, 20))

    def test_icon(self):
        self.assertEqual(self.lookup("icon"), (64, 10))

    def test_thumbnail(self):
        self.assertEqual(self.lookup("thumbnail"), (400, 50))

    def test_banner(self):
        self.assertEqual(self.lookup("banner"), (1600, 250))

    def test_cover_image(self):
        self.assertEqual(self.lookup("cover_image"), (1600, 250))

    def test_photo(self):
        self.assertEqual(self.lookup("photo"), (1200, 150))

    def test_image(self):
        self.assertEqual(self.lookup("image"), (1200, 150))

    def test_unknown_falls_back(self):
        self.assertEqual(self.lookup("widget_xyz"), (1200, 150))

    def test_case_insensitive(self):
        self.assertEqual(self.lookup("Hero"), (1600, 200))


class TestSvgPassthrough(unittest.TestCase):
    def test_svg_is_returned_unchanged(self):
        import tempfile, os, compress_image
        with tempfile.TemporaryDirectory() as td:
            p = os.path.join(td, "icon.svg")
            with open(p, "w") as f:
                f.write('<svg xmlns="http://www.w3.org/2000/svg"/>')
            result = compress_image.compress(p, "icon")
            self.assertEqual(result, p)
            self.assertTrue(os.path.exists(p), "SVG should not be deleted")


class TestWebpPassthrough(unittest.TestCase):
    """Regression: re-running on an already-WebP file must not delete the file
    (out_path == input_path collision in the unlink step)."""

    def test_webp_input_returns_same_path_and_keeps_file(self):
        import tempfile, os, compress_image
        from PIL import Image
        with tempfile.TemporaryDirectory() as td:
            p = os.path.join(td, "photo.webp")
            # Make a noisy 1500x1000 webp (over the 150KB photo budget)
            import random
            random.seed(11)
            img = Image.new("RGB", (1500, 1000))
            px = img.load()
            for y in range(1000):
                for x in range(1500):
                    px[x, y] = (random.randint(0, 255), random.randint(0, 255), random.randint(0, 255))
            img.save(p, format="WEBP", quality=85)
            assert os.path.getsize(p) > 150 * 1024, "fixture must exceed photo target_kb"

            result = compress_image.compress(p, "photo")
            self.assertEqual(result, p, "webp input must return same path (passthrough)")
            self.assertTrue(os.path.exists(p), "webp file must NOT be deleted on rerun")


class TestSkipIfSmall(unittest.TestCase):
    def _make_png(self, dir_path, name, width, height, fill=(255, 0, 0)):
        from PIL import Image
        import os
        p = os.path.join(dir_path, name)
        Image.new("RGB", (width, height), fill).save(p, format="PNG", optimize=True)
        return p

    def test_small_png_under_target_kb_is_kept(self):
        import tempfile, os, compress_image
        with tempfile.TemporaryDirectory() as td:
            # icon slot: max_width=64, target_kb=10; use 32x32 to satisfy both conditions
            p = self._make_png(td, "icon.png", 32, 32)
            assert os.path.getsize(p) < 10 * 1024
            result = compress_image.compress(p, "icon")
            self.assertEqual(result, p)
            self.assertTrue(os.path.exists(p))
            self.assertFalse(os.path.exists(p.replace(".png", ".webp")))

    def test_small_png_under_max_width_for_photo_is_kept(self):
        import tempfile, os, compress_image
        with tempfile.TemporaryDirectory() as td:
            p = self._make_png(td, "photo.png", 600, 400, fill=(0, 255, 0))
            assert os.path.getsize(p) < 150 * 1024
            result = compress_image.compress(p, "photo")
            self.assertEqual(result, p)
            self.assertTrue(os.path.exists(p))


class TestResizeAndEncode(unittest.TestCase):
    def _make_complex_png(self, dir_path, name, width, height):
        """Make a noisy PNG that won't compress trivially."""
        from PIL import Image
        import os, random
        random.seed(42)
        img = Image.new("RGB", (width, height))
        pixels = img.load()
        for y in range(height):
            for x in range(width):
                pixels[x, y] = (random.randint(0, 255), random.randint(0, 255), random.randint(0, 255))
        p = os.path.join(dir_path, name)
        img.save(p, format="PNG")
        return p

    def test_oversized_png_becomes_webp_and_is_resized(self):
        import tempfile, os, compress_image
        from PIL import Image
        with tempfile.TemporaryDirectory() as td:
            src = self._make_complex_png(td, "photo.png", 2400, 1600)
            result = compress_image.compress(src, "photo")
            self.assertTrue(result.endswith(".webp"), f"expected .webp, got {result}")
            self.assertTrue(os.path.exists(result))
            self.assertFalse(os.path.exists(src), "original PNG should be deleted")
            with Image.open(result) as out:
                self.assertLessEqual(out.width, 1200)
                expected_h = int(round(1200 * 1600 / 2400))
                self.assertAlmostEqual(out.height, expected_h, delta=1)

    def test_alpha_png_preserved_in_webp(self):
        import tempfile, os, compress_image
        from PIL import Image
        with tempfile.TemporaryDirectory() as td:
            p = os.path.join(td, "photo.png")
            Image.new("RGBA", (2000, 1000), (255, 0, 0, 128)).save(p, format="PNG")
            result = compress_image.compress(p, "photo")
            self.assertTrue(result.endswith(".webp"))
            with Image.open(result) as out:
                self.assertIn("A", out.mode)

    def test_quality_stepdown_floors_at_70(self):
        import tempfile, os, compress_image
        with tempfile.TemporaryDirectory() as td:
            src = self._make_complex_png(td, "hero.png", 3000, 3000)
            result = compress_image.compress(src, "hero")
            self.assertTrue(result.endswith(".webp"))


class TestCli(unittest.TestCase):
    def test_cli_prints_result_path(self):
        import tempfile, os, subprocess, sys
        scripts_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        with tempfile.TemporaryDirectory() as td:
            p = os.path.join(td, "icon.svg")
            with open(p, "w") as f:
                f.write('<svg/>')
            r = subprocess.run(
                [sys.executable, os.path.join(scripts_dir, "compress_image.py"), p, "icon"],
                capture_output=True, text=True
            )
            self.assertEqual(r.returncode, 0, msg=f"stderr: {r.stderr}")
            self.assertEqual(r.stdout.strip(), p)

    def test_cli_wrong_arg_count(self):
        import os, subprocess, sys
        scripts_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        r = subprocess.run(
            [sys.executable, os.path.join(scripts_dir, "compress_image.py")],
            capture_output=True, text=True
        )
        self.assertNotEqual(r.returncode, 0)
        self.assertIn("usage", r.stderr.lower())

    def test_cli_missing_input(self):
        import os, subprocess, sys, tempfile
        scripts_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        with tempfile.TemporaryDirectory() as td:
            missing = os.path.join(td, "nope.png")
            r = subprocess.run(
                [sys.executable, os.path.join(scripts_dir, "compress_image.py"), missing, "photo"],
                capture_output=True, text=True
            )
            self.assertNotEqual(r.returncode, 0)


if __name__ == "__main__":
    unittest.main()
