---
name: autoresearch
description: Guides autonomous ML research with karpathy/autoresearch. Activates when working inside an autoresearch repo, running LLM training experiments, modifying train.py for autonomous agent iteration, or editing program.md research instructions. Triggers on phrases like "run an experiment", "modify train.py", "start autoresearch", "kick off experiments", "improve val_bpb", or "edit program.md".
---

# Autoresearch

Autonomous LLM training experimentation framework by [@karpathy](https://github.com/karpathy/autoresearch). An AI agent modifies `train.py`, runs 5-minute training experiments, evaluates `val_bpb`, keeps improvements, discards regressions, and repeats — hundreds of iterations overnight.

## Three Files That Matter

| File | Who Edits | Purpose |
|------|-----------|---------|
| `prepare.py` | **Never touch** | Constants, data download, tokenizer training, dataloader, eval utilities |
| `train.py` | **Agent only** | GPT model, Muon+AdamW optimizer, training loop — everything is fair game |
| `program.md` | **Human only** | Agent instructions — your "research org code" |

## Setup (One-Time)

```bash
# Install uv if needed
curl -LsSf https://astral.sh/uv/install.sh | sh

# Install dependencies
uv sync

# Download data + train tokenizer (~2 min, run once)
uv run prepare.py

# Verify setup with a single 5-min run
uv run train.py
```

## Starting an Autonomous Session

Once setup is verified, prompt the agent:

```
Have a look at program.md and let's kick off a new experiment! Let's do the setup first.
```

Disable all permissions that aren't needed — the agent only needs to read/write `train.py` and run `uv run train.py`.

## Experiment Loop

Each iteration:
1. Read `program.md` for research direction
2. Read current `train.py` to understand baseline
3. Propose a change (architecture, hyperparams, optimizer, batch size, etc.)
4. Apply the change to `train.py`
5. Run `uv run train.py` — exactly 5 minutes wall clock
6. Read `val_bpb` from output — **lower is better**
7. Keep change if improved, revert if not
8. Log experiment result and repeat

Expect ~12 experiments/hour, ~100 experiments overnight.

## What to Modify in train.py

Everything in `train.py` is fair game:
- **Architecture** — depth, width, attention heads, activation functions, normalization
- **Attention pattern** — `WINDOW_PATTERN` (default `"SSSL"` — alternating banded + full)
- **Optimizer** — Muon vs AdamW ratios, learning rate, weight decay, scheduler
- **Batch size** — `TOTAL_BATCH_SIZE`, `DEVICE_BATCH_SIZE`
- **Model depth** — `DEPTH` is the primary complexity knob (default: 8)

## Metric: val_bpb

- **val_bpb** = validation bits per byte
- Lower is better
- Vocab-size-independent — fair comparison across architectural changes
- Defined and computed in `prepare.py` (do not modify)
- Use this as the sole accept/reject criterion

## Editing program.md (Human's Job)

`program.md` is your research org configuration. Iterate it to:
- Set a research direction (e.g. "focus on attention efficiency")
- Add constraints (e.g. "keep DEPTH ≤ 12")
- Document what's been tried and what worked
- Add multi-agent coordination if running parallel agents
- Guide the agent toward promising areas based on prior results

The better your `program.md`, the faster research progresses.

## Platform Notes

**Default:** Single NVIDIA GPU (H100 tested). The fixed 5-minute budget means results are platform-specific — not comparable across machines.

**Smaller compute (Mac, CPU, AMD):** See `references/small-compute.md` for tuning guidance and community forks.

## Key Design Constraints

- **Single file scope** — Agent only touches `train.py`. Keeps diffs reviewable.
- **Fixed time budget** — 5 min wall clock (excluding startup/compilation). All experiments are directly comparable.
- **One metric** — `val_bpb` only. No other success criteria.
- **Self-contained** — PyTorch + a few small packages. One GPU, one file, one metric.
