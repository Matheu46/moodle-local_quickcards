# QuickCards (`local_quickcards`)

QuickCards is a spaced repetition flashcard plugin for Moodle 4.x and 5.x. It enables learners to create, study, and master key concepts using the SuperMemo SM-2 algorithm directly within Moodle.

---

## Access Control & Authorization Flow

QuickCards implements a three-tier defense-in-depth model for access control:

```mermaid
flowchart TD
    A["User Request"] --> B{"1. Plugin Enabled?<br/>(settings.php)"}
    B -- No --> B1["Blocked<br/>(plugindisabled)"]
    B -- Yes --> C{"2. Has Capability?<br/>(local/quickcards:use)"}
    C -- No --> C1["Blocked by Moodle Core<br/>(require_capability)"]
    C -- Yes --> D{"3. In Allowed Cohorts?<br/>(or no cohorts configured)"}
    D -- No --> D1["Blocked<br/>(accessrestricted)"]
    D -- Yes --> E["Access Granted to QuickCards!"]
```

### Access Control Layers

1. **Global Site Setting (`local_quickcards/enabled`)**:
   - Master on/off switch managed in *Site administration > Plugins > Local plugins > QuickCards*.
   - When disabled, all endpoints, user action menu items, and AJAX web services are completely blocked.

2. **Moodle Capability (`local/quickcards:use`)**:
   - Standard Moodle role capability defined in `db/access.php`.
   - Allows fine-grained permission assignment by role (e.g., student, authenticated user, custom roles).

3. **Cohort Restriction (`local_quickcards/allowedcohorts`)**:
   - Multi-select setting in *Site administration > Plugins > Local plugins > QuickCards*.
   - **Open by default**: If no cohorts are selected, all permitted users can access.
   - **Restricted**: If one or more cohorts are selected, only members of those cohorts can access.
   - **Admin bypass**: Site administrators (`is_siteadmin()`) always retain access for testing and maintenance.

---

## Key Features

- **SM-2 Spaced Repetition**: Intelligent review intervals based on card recall difficulty (*Again*, *Hard*, *Good*, *Easy*).
- **Deck & Card Organization**: Organize cards into general decks or associate them with specific enrolled courses.
- **Import & Export**:
  - **QuickCards JSON**: Full deck backup with optional SM-2 study progress preservation.
  - **CSV / TSV**: Seamless interoperability with Anki, Quizlet, and spreadsheet editors.
- **Interactive Study Session**: Keyboard shortcuts (`Space` to flip, `1-4` for recall ratings), flip card animations, and auto-scheduling.
- **Deep Moodle Integration**: Linked from the user avatar action menu and custom navigation hooks.
