#!/usr/bin/env python3
"""
VRTX CRM Feature Testing Script
Tests all sidebar features and generates a completeness report
"""

import requests
import json
import time
from datetime import datetime

BASE_URL = "http://techco.vrtx.local:8000/api/v1"
TOKEN = None

def get_token():
    """Get auth token"""
    resp = requests.post(f"{BASE_URL}/auth/login", json={
        "email": "bob@techco.com",
        "password": "password123"
    }, headers={"Accept": "application/json"})
    if resp.status_code == 200:
        return resp.json()["data"]["token"]
    return None

def api_get(endpoint):
    """Make authenticated GET request"""
    time.sleep(0.15)  # Rate limit protection
    headers = {
        "Authorization": f"Bearer {TOKEN}",
        "Accept": "application/json"
    }
    try:
        resp = requests.get(f"{BASE_URL}{endpoint}", headers=headers, timeout=10)
        return resp.status_code, resp.json() if resp.status_code < 500 else {}
    except Exception as e:
        return 500, {"error": str(e)}

def api_post(endpoint, data=None):
    """Make authenticated POST request"""
    headers = {
        "Authorization": f"Bearer {TOKEN}",
        "Accept": "application/json",
        "Content-Type": "application/json"
    }
    try:
        resp = requests.post(f"{BASE_URL}{endpoint}", json=data or {}, headers=headers, timeout=10)
        return resp.status_code, resp.json() if resp.status_code < 500 else {}
    except Exception as e:
        return 500, {"error": str(e)}

def test_feature(name, endpoints, description):
    """Test a feature and return results"""
    results = {
        "name": name,
        "description": description,
        "status": "OK",
        "endpoints_tested": 0,
        "endpoints_passed": 0,
        "details": []
    }

    for endpoint, method in endpoints:
        results["endpoints_tested"] += 1
        if method == "GET":
            status, data = api_get(endpoint)
        else:
            status, data = api_post(endpoint)

        passed = status in [200, 201, 422]  # 422 is validation error which means endpoint works
        if passed:
            results["endpoints_passed"] += 1
            results["details"].append(f"✓ {method} {endpoint} - {status}")
        else:
            results["details"].append(f"✗ {method} {endpoint} - {status}: {data.get('message', 'Unknown error')[:50]}")

    if results["endpoints_passed"] < results["endpoints_tested"]:
        if results["endpoints_passed"] == 0:
            results["status"] = "FAILED"
        else:
            results["status"] = "PARTIAL"

    return results

def main():
    global TOKEN
    TOKEN = get_token()
    if not TOKEN:
        print("Failed to get auth token")
        return

    print("=" * 60)
    print("VRTX CRM Feature Completeness Test")
    print(f"Date: {datetime.now().strftime('%Y-%m-%d %H:%M')}")
    print("=" * 60)
    print()

    # Define all features to test
    features = [
        # CORE NAVIGATION
        ("Modules", [
            ("/modules", "GET"),
            ("/modules/active", "GET"),
        ], "Dynamic CRM modules - Contacts, Deals, Organizations, etc."),

        ("Records (Contacts)", [
            ("/records/contacts", "GET"),
            ("/records/contacts/lookup", "GET"),
        ], "CRUD operations for module records with filtering, sorting, pagination"),

        ("Views", [
            ("/views/contacts", "GET"),
            ("/views/contacts/default", "GET"),
            ("/views/contacts/kanban-fields", "GET"),
        ], "Custom views - list views, kanban views, saved filters"),

        # CORE FEATURES
        ("Blueprints", [
            ("/blueprints", "GET"),
        ], "State machine workflows - define stages, transitions, approvals"),

        ("Workflows", [
            ("/workflows", "GET"),
            ("/workflows/trigger-types", "GET"),
            ("/workflows/action-types", "GET"),
        ], "Automation rules - triggers, conditions, actions"),

        ("Web Forms", [
            ("/web-forms", "GET"),
        ], "Public lead capture forms with custom fields"),

        # SALES & REVENUE
        ("Forecasts", [
            ("/forecasts", "GET"),
            ("/forecasts/deals", "GET"),
        ], "Sales forecasting with pipeline analysis"),

        ("Quotas", [
            ("/quotas", "GET"),
            ("/quotas/my-progress", "GET"),
        ], "Sales quotas and target tracking"),

        ("Goals", [
            ("/goals", "GET"),
        ], "Goal setting and progress tracking"),

        ("Products", [
            ("/products", "GET"),
        ], "Product catalog for quotes and invoices"),

        ("Quotes", [
            ("/quotes", "GET"),
        ], "Sales quotes with line items and PDF generation"),

        ("Invoices", [
            ("/invoices", "GET"),
        ], "Invoice management with payments"),

        ("Deal Rooms", [
            ("/deal-rooms", "GET"),
        ], "Collaborative deal spaces with documents and chat"),

        ("Competitors", [
            ("/competitors", "GET"),
        ], "Competitor battlecards and analysis"),

        ("Scenarios", [
            ("/scenarios", "GET"),
        ], "What-if scenario planning for deals"),

        ("Rotting Alerts", [
            ("/rotting/settings", "GET"),
            ("/rotting/alerts", "GET"),
        ], "Deal staleness alerts and notifications"),

        ("Duplicates", [
            ("/duplicates/rules", "GET"),
        ], "Duplicate detection and merging"),

        # ANALYTICS
        ("Reports", [
            ("/reports", "GET"),
        ], "Custom reports with charts and tables"),

        ("Dashboards", [
            ("/dashboards", "GET"),
        ], "Customizable dashboards with widgets"),

        ("Revenue Graph", [
            ("/graph/nodes", "GET"),
            ("/graph/edges", "GET"),
        ], "Visual revenue flow analysis"),

        # COMMUNICATION
        ("Email", [
            ("/email-accounts", "GET"),
            ("/email-templates", "GET"),
        ], "Email integration with Gmail/Outlook sync"),

        ("Scheduling", [
            ("/scheduling/pages", "GET"),
            ("/scheduling/availability", "GET"),
        ], "Meeting scheduling with Calendly-like booking"),

        # AUTOMATION
        ("Process Recorder", [
            ("/recordings", "GET"),
        ], "Record actions to create workflows"),

        # SETTINGS
        ("Roles & Permissions", [
            ("/rbac/roles", "GET"),
            ("/rbac/permissions", "GET"),
        ], "Role-based access control"),

        ("API Keys", [
            ("/api-keys", "GET"),
        ], "API key management for integrations"),

        ("Webhooks", [
            ("/webhooks", "GET"),
            ("/incoming-webhooks", "GET"),
        ], "Outgoing and incoming webhook configuration"),

        # CHANNELS & INTEGRATIONS
        ("Live Chat", [
            ("/chat/widgets", "GET"),
            ("/chat/conversations", "GET"),
        ], "Website chat widget with visitor tracking"),

        ("WhatsApp", [
            ("/whatsapp/connections", "GET"),
            ("/whatsapp/templates", "GET"),
        ], "WhatsApp Business API integration"),

        ("SMS", [
            ("/sms/connections", "GET"),
            ("/sms/templates", "GET"),
            ("/sms/campaigns", "GET"),
        ], "SMS messaging with Twilio"),

        ("Team Chat", [
            ("/team-chat/connections", "GET"),
        ], "Slack/Teams notifications"),

        ("Shared Inbox", [
            ("/inboxes", "GET"),
        ], "Shared email inbox for teams"),

        ("Call Center", [
            ("/calls/providers", "GET"),
            ("/calls", "GET"),
        ], "VoIP calling with Twilio"),

        ("Meetings", [
            ("/meetings", "GET"),
            ("/meetings/upcoming", "GET"),
        ], "Meeting intelligence and analytics"),

        ("Marketing Campaigns", [
            ("/campaigns", "GET"),
        ], "Email and multi-channel campaigns"),

        ("Cadences", [
            ("/cadences", "GET"),
        ], "Sales sequences and follow-ups"),
    ]

    all_results = []
    passed = 0
    partial = 0
    failed = 0

    for name, endpoints, desc in features:
        result = test_feature(name, endpoints, desc)
        all_results.append(result)
        if result["status"] == "OK":
            passed += 1
            icon = "✅"
        elif result["status"] == "PARTIAL":
            partial += 1
            icon = "⚠️"
        else:
            failed += 1
            icon = "❌"

        print(f"{icon} {name}: {result['status']} ({result['endpoints_passed']}/{result['endpoints_tested']} endpoints)")
        print(f"   {desc}")
        for detail in result["details"]:
            print(f"   {detail}")
        print()

    # Summary
    print("=" * 60)
    print("SUMMARY")
    print("=" * 60)
    print(f"Total Features: {len(features)}")
    print(f"✅ Passed: {passed}")
    print(f"⚠️ Partial: {partial}")
    print(f"❌ Failed: {failed}")
    print()

    # Generate report
    completion = (passed + partial * 0.5) / len(features) * 100
    print(f"Overall Completion: {completion:.1f}%")

if __name__ == "__main__":
    main()
