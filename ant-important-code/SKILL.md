---
name: ant-important-code
description: Enforces Antikode Architecture coding discipline—request-driven work, no implementation until asked, simple solutions, sparse comments, concise communication, Laravel thin controllers with final classes, DI, and services for logic. Use when writing or reviewing PHP/Laravel in this repo, when the user asks to keep changes small, avoid unsolicited code, or align with Antikode-style standards.
---

# Antikode Architecture — important coding discipline

Apply these constraints by default for this codebase.

## Code generation

- Do not write or change code unless the user explicitly asks for implementation.
- Do not assume extra features, files, or refactors beyond what they requested.

## Simplicity

- Prefer straightforward solutions; avoid overengineering and unnecessary abstractions.
- Keep diffs minimal: only what is needed for the task.

## Comments and documentation

- Do not add inline or explanatory comments in code.
- Use PHPDoc on methods only when required (public APIs, non-obvious contracts).
- Prefer self-explanatory names and structure over commentary.

## Communication

- Be concise and direct; stay on what was asked.
- Skip lecturing on obvious points.
- Ask clarifying questions when requirements are unclear.

## Laravel and PHP

- Follow Laravel conventions.
- Use `final` classes where appropriate for controllers, models, and services.
- Use constructor dependency injection.
- Keep controllers thin; put business logic in services.
