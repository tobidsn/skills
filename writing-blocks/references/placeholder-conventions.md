# Image placeholder conventions

When rendering `example.json` for a generated block, image slots get URLs of the form:

```
https://picsum.photos/<W>/<H>
```

`<W>x<H>` is resolved in this order:

1. `default_image_dimensions:` from the library entry (if present and matches the slot name).
2. **Slot-name pattern table** below.
3. Fallback: `800x600`.

## Slot-name pattern table

Match against the snake_case slot name (case-insensitive). First match wins.

| Pattern (regex)                                  | Picsum dimensions | Compression max width | Compression target KB |
|--------------------------------------------------|-------------------|-----------------------|-----------------------|
| `^(hero\|hero_.+\|.+_hero)$`                       | `1600x900`        | 1600                  | 200                   |
| `^(banner\|cover\|cover_image\|.+_banner)$`         | `1600x600`        | 1600                  | 250                   |
| `^(thumbnail\|thumb\|.+_thumbnail\|.+_thumb)$`      | `400x300`         | 400                   | 50                    |
| `^(avatar\|.+_avatar\|profile\|profile_image\|portrait)$` | `200x200`    | 200                   | 30                    |
| `^(logo\|logo_.+\|.+_logo\|brand_mark)$`            | `240x80`          | 240                   | 20                    |
| `^(icon\|.+_icon\|.+_glyph)$`                      | `64x64`           | 64                    | 10                    |
| `^(photo\|.+_photo\|image\|.+_image\|picture\|.+_picture)$` | `1200x800`  | 1200                  | 150                   |

Fallback (no pattern match): picsum `800x600`; compression max width `1200`, target KB `150`.

## Constructing the URL

Once dimensions are resolved, the URL is literally:

```
https://picsum.photos/<W>/<H>
```

No seed parameter, no query string. picsum returns a random image per request, which is fine for example data.

## Why picsum

Trade-off accepted in the spec: visual realism > offline-purity. example.json is a preview artifact; consumers replace placeholder URLs with real assets when wiring up production data.

## Compression usage (v2.2+)

The `Compression max width` and `Compression target KB` columns above feed `scripts/compress_image.py`, which runs:

- Automatically after the Figma flow (Flow 1 step 4.5) on every downloaded raster asset.
- Manually via `/writing-blocks compress <path-or-slug>` (Flow 5).

For each raster asset:

1. SVG → passthrough (untouched).
2. Already under `target_kb` AND under `max_width` → passthrough (no quality loss on already-optimal images).
3. Otherwise → resize to `max_width` (preserve aspect, never upscale), encode WebP with quality stepdown 85 → 80 → 75 → 70 (floor). Delete the original raster. Return the new `.webp` path.

The script is invoked via `uv run` (which uses PEP 723 inline metadata to install Pillow into a cached ephemeral venv on first use). Falls back to plain `python3` if Pillow is in system Python.

The script's hard-coded `SLOT_TABLE` mirrors the patterns above. If you change the table here, mirror the change in `scripts/compress_image.py`.
