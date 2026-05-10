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

| Pattern (regex)                                  | Dimensions  | Notes                              |
|--------------------------------------------------|-------------|------------------------------------|
| `^(hero\|hero_.+\|.+_hero)$`                       | `1600x900`  | Hero/banner images                 |
| `^(avatar\|.+_avatar\|profile\|profile_image\|portrait)$` | `200x200`  | Square avatars/portraits           |
| `^(logo\|logo_.+\|.+_logo\|brand_mark)$`            | `240x80`    | Wordmark / banner logos            |
| `^(icon\|.+_icon\|.+_glyph)$`                      | `64x64`     | Tiny square icons                  |
| `^(thumbnail\|thumb\|.+_thumbnail\|.+_thumb)$`      | `400x300`   | Thumbnails                         |
| `^(banner\|cover\|cover_image\|.+_banner)$`         | `1600x600`  | Wide banner art                    |
| `^(photo\|.+_photo\|image\|.+_image\|picture\|.+_picture)$` | `1200x800` | Generic content photos             |

## Constructing the URL

Once dimensions are resolved, the URL is literally:

```
https://picsum.photos/<W>/<H>
```

No seed parameter, no query string. picsum returns a random image per request, which is fine for example data.

## Why picsum

Trade-off accepted in the spec: visual realism > offline-purity. example.json is a preview artifact; consumers replace placeholder URLs with real assets when wiring up production data.
