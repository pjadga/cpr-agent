#!/bin/bash

# Cyberpunk Agent Test Runner
# This script runs all test suites for the application

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║         Cyberpunk Agent - Test Suite Runner               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Function to print section headers
print_section() {
    echo ""
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  $1${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# Parse command line arguments
SUITE=""
COVERAGE=false
PARALLEL=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --backend)
            SUITE="backend"
            shift
            ;;
        --frontend)
            SUITE="frontend"
            shift
            ;;
        --unit)
            SUITE="unit"
            shift
            ;;
        --coverage)
            COVERAGE=true
            shift
            ;;
        --parallel)
            PARALLEL=true
            shift
            ;;
        --help)
            echo "Usage: ./run-tests.sh [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --backend     Run only backend tests"
            echo "  --frontend    Run only frontend tests"
            echo "  --unit        Run only unit tests"
            echo "  --coverage    Generate coverage reports"
            echo "  --parallel    Run tests in parallel (faster)"
            echo "  --help        Show this help message"
            echo ""
            echo "Examples:"
            echo "  ./run-tests.sh                    # Run all tests"
            echo "  ./run-tests.sh --backend          # Run backend tests only"
            echo "  ./run-tests.sh --coverage         # Run all tests with coverage"
            echo "  ./run-tests.sh --backend --parallel  # Run backend tests in parallel"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Check if we're in the project root
if [ ! -f "artisan" ]; then
    echo -e "${RED}Error: This script must be run from the project root directory${NC}"
    exit 1
fi

# Backend Tests
if [ -z "$SUITE" ] || [ "$SUITE" = "backend" ]; then
    print_section "Backend Tests (Laravel/PHPUnit)"

    BACKEND_CMD="ddev artisan test"

    if [ "$SUITE" = "backend" ]; then
        BACKEND_CMD="$BACKEND_CMD --testsuite=Backend"
    fi

    if [ "$COVERAGE" = true ]; then
        BACKEND_CMD="$BACKEND_CMD --coverage"
    fi

    if [ "$PARALLEL" = true ]; then
        BACKEND_CMD="$BACKEND_CMD --parallel"
    fi

    echo -e "${BLUE}Running: $BACKEND_CMD${NC}"
    echo ""

    if $BACKEND_CMD; then
        echo ""
        echo -e "${GREEN}✓ Backend tests passed${NC}"
    else
        echo ""
        echo -e "${RED}✗ Backend tests failed${NC}"
        exit 1
    fi
fi

# Unit Tests
if [ -z "$SUITE" ] || [ "$SUITE" = "unit" ]; then
    print_section "Unit Tests (PHPUnit)"

    UNIT_CMD="ddev artisan test --testsuite=Unit"

    if [ "$COVERAGE" = true ]; then
        UNIT_CMD="$UNIT_CMD --coverage"
    fi

    if [ "$PARALLEL" = true ]; then
        UNIT_CMD="$UNIT_CMD --parallel"
    fi

    echo -e "${BLUE}Running: $UNIT_CMD${NC}"
    echo ""

    if $UNIT_CMD; then
        echo ""
        echo -e "${GREEN}✓ Unit tests passed${NC}"
    else
        echo ""
        echo -e "${RED}✗ Unit tests failed${NC}"
        exit 1
    fi
fi

# Frontend Tests
if [ -z "$SUITE" ] || [ "$SUITE" = "frontend" ]; then
    print_section "Frontend Tests (Vitest)"

    # Check if node_modules exists
    if [ ! -d "node_modules" ]; then
        echo -e "${YELLOW}Installing npm dependencies...${NC}"
        npm install
    fi

    FRONTEND_CMD="npm run test"

    if [ "$COVERAGE" = true ]; then
        FRONTEND_CMD="npm run test:coverage"
    fi

    echo -e "${BLUE}Running: $FRONTEND_CMD${NC}"
    echo ""

    if $FRONTEND_CMD; then
        echo ""
        echo -e "${GREEN}✓ Frontend tests passed${NC}"
    else
        echo ""
        echo -e "${RED}✗ Frontend tests failed${NC}"
        exit 1
    fi
fi

# Summary
echo ""
print_section "Test Summary"

if [ -z "$SUITE" ]; then
    echo -e "${GREEN}✓ All test suites passed successfully!${NC}"
else
    echo -e "${GREEN}✓ $SUITE test suite passed successfully!${NC}"
fi

if [ "$COVERAGE" = true ]; then
    echo ""
    echo -e "${BLUE}Coverage reports generated:${NC}"
    echo "  - Backend: coverage/index.html (if Xdebug/PCOV enabled)"
    echo "  - Frontend: coverage/index.html"
fi

echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║                    Tests Complete!                         ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""
