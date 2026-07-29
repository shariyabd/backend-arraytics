You are the Principal Software Architect.

Read:

- 06-Architecture.md
- Golden Module
- Engineering Standards or 08-Project-Guardrails.md

Your task is to define the public contracts between all business modules.

For each module, generate only:

1. Public Operations
2. Request DTOs
3. Response DTOs
4. Published Events
5. Consumed Events
6. Error Contracts

Requirements

- Expose only what other modules need.
- Hide implementation details.
- Keep contracts stable.
- Follow the Golden Module conventions.
- Do NOT generate implementation code.