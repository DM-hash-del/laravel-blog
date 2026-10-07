---
name: review-decisions
description: "Look up what was agreed for THIS project (the Laravel AI Blog) in docs/decisions.md. Use only when a question or task depends on a project-specific decision, e.g. \"what did we agree about slugs?\", \"is a comments table allowed?\", \"which build stage comes next?\", or when CLAUDE.md's distilled rules don't answer a design question. Do not use for general Laravel, PHP, Tailwind or Pest questions that don't hinge on this project's agreed choices."
---

# Review Decisions

`docs/decisions.md` is the single source of truth for what was agreed in this project. CLAUDE.md carries only a distilled version. Use this skill to answer from the full document.

## Process

1. **Read `docs/decisions.md` in full.** Don't rely on the CLAUDE.md summary or on memory, because details live in the step tables and checklists.
2. **Find the step that covers the question.** Check the decision table and both the **Should be there** and **Should not be there** lists. A question may span several steps.
3. **Answer from what the document says, and name the step**, for example "Step 6: Routing, controllers and validation". Quote or closely paraphrase the decision rather than restating it in your own terms.
4. **If the document doesn't cover it, say so and ask.** Do not invent a decision, and do not present a Laravel default or your own preference as something that was agreed. You may suggest an option, clearly labelled as a suggestion.
5. **If following a decision looks like a mistake, say so.** That includes a decision that conflicts with another step, is insecure, or no longer fits the code. Explain why, and don't quietly work around it. If the user then changes the decision, update `docs/decisions.md` in the same change.
