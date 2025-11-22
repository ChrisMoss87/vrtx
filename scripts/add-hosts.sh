#!/bin/bash

# Add tenant domains to /etc/hosts
echo "Adding tenant domains to /etc/hosts..."
echo ""
echo "Please run the following commands:"
echo ""
echo "sudo tee -a /etc/hosts << 'EOF'"
echo "127.0.0.1 acme.vrtx.local"
echo "127.0.0.1 techco.vrtx.local"
echo "127.0.0.1 startup.vrtx.local"
echo "127.0.0.1 crm.startup.com"
echo "EOF"
echo ""
echo "Or add these lines manually to /etc/hosts:"
echo "127.0.0.1 acme.vrtx.local"
echo "127.0.0.1 techco.vrtx.local"
echo "127.0.0.1 startup.vrtx.local"
echo "127.0.0.1 crm.startup.com"
