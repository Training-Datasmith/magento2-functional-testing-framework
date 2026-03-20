# Architecture: magento2-functional-testing-framework (MFTF)

## Purpose

The Magento Functional Testing Framework (MFTF) is a Codeception-based end-to-end testing framework for Magento 2. Tests are authored in XML and compiled to PHP Codeception test classes, enabling non-PHP test authors to write browser-based UI tests.

## Directory Structure

```
src/Magento/FunctionalTestingFramework/
  Console/                   — CLI commands (generate tests, run tests, cleanup)
  Test/
    Objects/                 — In-memory representations of XML test entities
      Test_Object.php        — A single test with actions and hooks
      Action_Object.php      — One test step (e.g., click, fillField, see)
      Action_Group_Object.php — Reusable group of actions
      Test_Hook_Object.php   — Before/after hooks
    Parsers/                 — Parse XML test files into object graphs
    Handlers/                — Singletons that cache parsed test/action-group objects
    Util/                    — Extractors: pull structured data from DOM nodes
    Config/                  — DOM-based XML config loaders and mergers
    Filter/                  — Test filtering by group, severity, include/exclude
  DataGenerator/
    Handlers/
      Data_Object_Handler.php   — Manages test fixture data (entities.xml)
      Persisted_Object_Handler.php — Tracks entities created during test runs
      Credential_Store.php      — Secure storage for test secrets (Vault, AWS SSM, file)
    Config/                   — DOM loaders for data and operation XML files
  Config/                    — Generic XML config infrastructure (DOM merging, readers, schema)
  Allure/                    — Allure report integration (attachments, events)
  Codeception/               — Codeception module and subscriber integration
  Code/Reader/               — PHP class reader (reflects generated test classes)
```

## Key Design Decisions

- **XML-to-PHP compilation**: MFTF compiles XML test definitions into PHP Codeception test classes at `bin/mftf generate:tests`. The generated files live in `generated/tests/`
- **Action groups**: Reusable sequences of actions are defined in `actionGroups/` XML and resolved at generation time via `ActionGroupObjectHandler`
- **Data entities**: Test fixtures are declarative XML entities (in `Data/`); MFTF creates them via API calls before tests and deletes them after
- **Secret storage**: Sensitive test data (passwords, API keys) is kept out of XML via pluggable `CredentialStore` backends (file, HashiCorp Vault, AWS Secrets Manager)
- **DOM merging**: Multiple XML files for the same resource (tests, data) are merged by MFTF's `Dom` class before parsing, enabling modular test organization across modules

## Extension Points

- Add new action types by extending the MFTF action library and registering them in `actionTypes`
- Add a custom credential storage backend by implementing the storage interface and configuring it in `.env`
- Filter tests by `@group` annotation, severity, or custom filter implementations

## Dependency Flow

```
XML test files (Test/, ActionGroup/, Data/, Section/, Page/)
  → Parsers → Object graph (TestObject, ActionObject, etc.)
  → Code generation → PHP Codeception test classes
  → Codeception runner → Browser via WebDriver
  → Allure report
```
