# BLOCK: hero

dimensions:
  width: 1440
  height: 720

layout:
  mode: vertical
  align: center

content:
  - eyebrow: { type: string }
  - title: { type: richtext }
  - description: { type: richtext }
  - primary_cta: { type: object, fields: [label: string, url: string] }
  - secondary_cta: { type: object, fields: [label: string, url: string] }
  - hero_image: { type: image }

default_image_dimensions:
  hero_image: 1600x900
