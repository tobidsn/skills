# Autoresearch on Small Compute

For running autoresearch on Macs, CPUs, or smaller GPUs instead of an H100.

## Community Forks

| Fork | Platform |
|------|----------|
| [miolini/autoresearch-macos](https://github.com/miolini/autoresearch-macos) | MacOS |
| [trevin-creator/autoresearch-mlx](https://github.com/trevin-creator/autoresearch-mlx) | MacOS (MLX) |
| [jsegov/autoresearch-win-rtx](https://github.com/jsegov/autoresearch-win-rtx) | Windows |
| [andyluo7/autoresearch](https://github.com/andyluo7/autoresearch) | AMD |

## Tuning Recommendations (from karpathy)

Use a lower-entropy dataset — e.g. [TinyStories](https://huggingface.co/datasets/karpathy/tinystories-gpt4-clean). Smaller models produce reasonable results on narrow-scope data.

### In `prepare.py`

| Parameter | Default | Small Compute |
|-----------|---------|---------------|
| `MAX_SEQ_LEN` | (large) | 256–512 |
| `EVAL_TOKENS` | (large) | Decrease proportionally |
| `vocab_size` | 8192 | 4096, 2048, 1024, or 256 (byte-level) |

### In `train.py`

| Parameter | Default | Small Compute |
|-----------|---------|---------------|
| `DEPTH` | 8 | 4 (primary complexity knob) |
| `TOTAL_BATCH_SIZE` | 2^17+ | 2^14 (~16K) or lower, keep powers of 2 |
| `DEVICE_BATCH_SIZE` | (default) | Increase slightly as `MAX_SEQ_LEN` decreases |
| `WINDOW_PATTERN` | `"SSSL"` | `"L"` (full attention only — banded is slow on small compute) |

## Notes

- Tokens per forward/backward pass = `MAX_SEQ_LEN × DEVICE_BATCH_SIZE` — tune together
- `WINDOW_PATTERN = "SSSL"` uses alternating banded attention which can be very slow on non-H100 hardware; switch to `"L"` first
- Results on small compute are not comparable to H100 runs — the 5-min budget adapts to your platform
