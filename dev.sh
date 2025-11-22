#!/bin/bash

set -e

echo "========================================="
echo "  VRTX CRM Development Environment"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to cleanup on exit
cleanup() {
    echo ""
    echo -e "${YELLOW}Shutting down services...${NC}"

    # Kill background processes
    if [ ! -z "$BACKEND_PID" ]; then
        kill $BACKEND_PID 2>/dev/null || true
    fi
    if [ ! -z "$FRONTEND_PID" ]; then
        kill $FRONTEND_PID 2>/dev/null || true
    fi

    # Stop Docker services
    echo -e "${BLUE}Stopping Docker services...${NC}"
    docker compose down

    echo -e "${GREEN}All services stopped${NC}"
    exit 0
}

# Trap SIGINT and SIGTERM
trap cleanup SIGINT SIGTERM

# Kill any existing Laravel/Vite processes
echo -e "${BLUE}Cleaning up existing processes...${NC}"
pkill -f "php artisan serve" 2>/dev/null || true
pkill -f "vite" 2>/dev/null || true
sleep 1

# Start Docker services
echo -e "${BLUE}Starting infrastructure (PostgreSQL, Redis, Mailhog)...${NC}"
docker compose up -d

# Wait for PostgreSQL to be ready
echo -e "${BLUE}Waiting for PostgreSQL...${NC}"
sleep 3

# Start backend
echo -e "${BLUE}Starting Laravel backend on http://localhost:8000...${NC}"
cd backend
php artisan serve --host=0.0.0.0 --port=8000 > ../logs/backend.log 2>&1 &
BACKEND_PID=$!
cd ..

# Wait a moment for backend to start
sleep 2

# Start frontend
echo -e "${BLUE}Starting SvelteKit frontend on http://localhost:5173...${NC}"
cd frontend
pnpm dev --host 0.0.0.0 > ../logs/frontend.log 2>&1 &
FRONTEND_PID=$!
cd ..

echo ""
echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}  All services started successfully!${NC}"
echo -e "${GREEN}=========================================${NC}"
echo ""
echo -e "  Frontend:  ${BLUE}http://localhost:5173${NC}"
echo -e "  Backend:   ${BLUE}http://localhost:8000${NC}"
echo -e "  Mailhog:   ${BLUE}http://localhost:8025${NC}"
echo -e "  PostgreSQL: ${BLUE}localhost:5433${NC}"
echo -e "  Redis:     ${BLUE}localhost:6379${NC}"
echo ""
echo -e "${YELLOW}Logs:${NC}"
echo -e "  Backend:   tail -f logs/backend.log"
echo -e "  Frontend:  tail -f logs/frontend.log"
echo ""
echo -e "${YELLOW}Press Ctrl+C to stop all services${NC}"
echo ""

# Wait for any process to exit
wait
