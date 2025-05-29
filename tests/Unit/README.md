# Unit Testing Guidelines

## Important: Shared WordPress Instance

All PHPUnit tests run against a **single, shared WordPress instance**. This means any changes made to the global state, such as:

*   Adding or modifying WordPress filters/actions
*   Setting or updating WordPress options (`update_option`)

**will persist across all subsequent tests** run in the same session. This can lead to unexpected failures and flaky tests if not handled carefully.

## Using Isolation Traits

To mitigate state leakage between tests, we provide two abstract base classes (implementing traits) that your test classes should extend **selectively**, based on what the test modifies:

1.  **`AbstractWPUnitTestWithSafeFiltering`**:
    *   **Use when:** Your test needs to add WordPress filters or actions (`add_filter`, `add_action`).
    *   **How it helps:** Provides `add_filter_with_safe_teardown` (works for actions too). Filters added using this method are automatically tracked and removed after each test (`tearDown`), preventing filter leakage.
    *   **Example:** Testing code that relies on a specific filter being present.

2.  **`AbstractWPUnitTestWithOptionIsolationAndSafeFiltering`**:
    *   **Use when:** Your test reads (`get_option`) or writes (`update_option`) WordPress options.
    *   **How it helps:** Extends the safe filtering above *and* intercepts `get_option` and `update_option` calls. It stores option values in a temporary, test-specific array instead of the database. This ensures option changes within one test do not affect others. It also provides helper methods like `mock_set_option`, `mock_get_option`, and assertions like `assertOptionUpdated`.
    *   **Example:** Testing settings functionality or code that relies on specific option values.

**Choose the appropriate base class based on the *minimum* isolation level your test requires.** If your test only adds filters, use `AbstractWPUnitTestWithSafeFiltering`. If it interacts with options (even indirectly), use `AbstractWPUnitTestWithOptionIsolationAndSafeFiltering`.

## Caution: Indirect Option Setting

Be particularly mindful of option setting. Even if your test method doesn't directly call `update_option`, the code under test might.

*   **Example:** A test interacting with a REST API endpoint might trigger code that saves settings via `update_option` deep within the API controller logic (e.g., as seen in `RestAPITest.php` scenarios).

If your test, or the code it executes, *could* potentially modify options, **you MUST use `AbstractWPUnitTestWithOptionIsolationAndSafeFiltering`** to prevent side effects on other tests. When in doubt, use the option isolation trait.
