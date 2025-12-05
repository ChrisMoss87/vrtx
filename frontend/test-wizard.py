from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()

    # Capture console messages
    console_messages = []
    page.on("console", lambda msg: console_messages.append(f"[{msg.type}] {msg.text}"))

    # Capture errors
    errors = []
    page.on("pageerror", lambda err: errors.append(str(err)))

    print("Navigating to wizard demo...")
    page.goto('http://acme.vrtx.local/wizard-demo')
    page.wait_for_load_state('networkidle')

    print("\n=== Console Messages ===")
    for msg in console_messages:
        print(msg)

    print("\n=== Page Errors ===")
    for err in errors:
        print(err)

    # Take a screenshot
    page.screenshot(path='/tmp/wizard-demo.png', full_page=True)
    print("\nScreenshot saved to /tmp/wizard-demo.png")

    # Check if the Next button exists and its state
    print("\n=== Button States ===")
    next_btn = page.locator('button:has-text("Next")')
    if next_btn.count() > 0:
        print(f"Next button found, disabled: {next_btn.is_disabled()}")
    else:
        print("Next button not found")

    browser.close()
