#!/usr/bin/env -S uv run --script
# /// script
# requires-python = ">=3.10"
# dependencies = ["Pillow"]
# ///
"""compress_image.py — writing-blocks v2.2 image pipeline.

Public API:
    compress(input_path: str | Path, slot_name: str) -> str
        Apply the per-asset decision tree (SVG passthrough → skip-if-small →
        resize+WebP+quality-stepdown). Returns the FINAL file path on disk.

CLI:
    uv run compress_image.py <input-path> <slot-name>
        (or `python3 compress_image.py …` if Pillow is in system Python)

The slot → (max_width, target_kb) table mirrors the conventions in
references/placeholder-conventions.md.
"""
from __future__ import annotations

import pathlib
import re


# Slot → (max_width, target_kb).
# Mirror of the table in references/placeholder-conventions.md.
# First match wins; case-insensitive.
SLOT_TABLE = [
    (r"^(hero|hero_.+|.+_hero)$",                            1600, 200),
    (r"^(banner|cover|cover_image|.+_banner)$",              1600, 250),
    (r"^(thumbnail|thumb|.+_thumbnail|.+_thumb)$",            400,  50),
    (r"^(avatar|.+_avatar|profile|profile_image|portrait)$",  200,  30),
    (r"^(logo|logo_.+|.+_logo|brand_mark)$",                  240,  20),
    (r"^(icon|.+_icon|.+_glyph)$",                             64,  10),
    (r"^(photo|.+_photo|image|.+_image|picture|.+_picture)$",1200, 150),
]
FALLBACK_MAX_WIDTH = 1200
FALLBACK_TARGET_KB = 150

QUALITY_STEPS = (85, 80, 75, 70)  # Stepdown ladder. Floor at 70.


def lookup_slot(slot_name):
    """Return (max_width_px, target_kb) for a slot name. Case-insensitive."""
    name = slot_name.lower()
    for pattern, max_w, target in SLOT_TABLE:
        if re.match(pattern, name):
            return (max_w, target)
    return (FALLBACK_MAX_WIDTH, FALLBACK_TARGET_KB)


def compress(input_path, slot_name):
    """Apply the per-asset decision tree.

    Returns the final on-disk path:
      - SVG or already-WebP: passthrough (input path returned, file untouched).
      - Already small (under target_kb AND under max_width): passthrough.
      - Otherwise: resize to max_width, encode WebP with quality stepdown,
        delete the original raster, return the new .webp path.
    """
    p = pathlib.Path(input_path)
    # SVG and WebP files are passthrough — already in efficient or target formats.
    # Re-encoding a WebP would risk deleting it when out_path == input_path.
    if p.suffix.lower() in (".svg", ".webp"):
        return str(p)

    max_w, target_kb = lookup_slot(slot_name)
    target_bytes = target_kb * 1024

    from PIL import Image
    with Image.open(p) as img:
        cur_w = img.width
        cur_h = img.height
        cur_bytes = p.stat().st_size

        if cur_bytes <= target_bytes and cur_w <= max_w:
            return str(p)

        if cur_w > max_w:
            new_h = int(round(max_w * cur_h / cur_w))
            resized = img.resize((max_w, new_h), Image.LANCZOS)
        else:
            resized = img.copy()

    out_path = p.with_suffix(".webp")
    for q in QUALITY_STEPS:
        save_kwargs = {"format": "WEBP", "quality": q, "method": 6}
        if resized.mode in ("RGBA", "LA"):
            save_kwargs["lossless"] = False
        resized.save(out_path, **save_kwargs)
        if out_path.stat().st_size <= target_bytes:
            break

    # Defensive: only delete the original if it's a different file from the output.
    if out_path.resolve() != p.resolve():
        p.unlink()
    return str(out_path)


if __name__ == "__main__":
    import sys
    if len(sys.argv) != 3:
        print("usage: compress_image.py <input-path> <slot-name>", file=sys.stderr)
        sys.exit(2)
    input_path, slot_name = sys.argv[1], sys.argv[2]
    if not pathlib.Path(input_path).exists():
        print(f"error: input not found: {input_path}", file=sys.stderr)
        sys.exit(3)
    try:
        result_path = compress(input_path, slot_name)
    except Exception as exc:
        print(f"error: {exc}", file=sys.stderr)
        sys.exit(4)
    print(result_path)
