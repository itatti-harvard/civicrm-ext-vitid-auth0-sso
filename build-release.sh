#!/bin/bash

# Build script for VIT ID Authentication Extension
# Creates a production-ready release package with all dependencies

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get version from info.xml (macOS compatible)
VERSION=$(sed -n 's/.*<version>\(.*\)<\/version>.*/\1/p' info.xml | head -n 1 | tr -d '[:space:]')

if [ -z "$VERSION" ]; then
    echo -e "${RED}Error: Could not extract version from info.xml${NC}"
    exit 1
fi

RELEASE_NAME="vitid_auth0-v${VERSION}"
RELEASE_FILE="${RELEASE_NAME}.zip"

echo -e "${GREEN}Building VIT ID Authentication Extension${NC}"
echo -e "${GREEN}Version: ${VERSION}${NC}"
echo ""

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Error: Composer is not installed${NC}"
    echo "Please install composer: https://getcomposer.org/download/"
    exit 1
fi

# Clean previous build artifacts
echo -e "${YELLOW}Cleaning previous builds...${NC}"
rm -rf vendor/
rm -f *.zip

# Install dependencies
echo -e "${YELLOW}Installing dependencies...${NC}"
composer install --no-dev --optimize-autoloader

if [ ! -d "vendor" ]; then
    echo -e "${RED}Error: vendor directory not created${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Dependencies installed${NC}"

# Create release package
echo -e "${YELLOW}Creating release package...${NC}"

# Create temporary directory for packaging
TEMP_DIR=$(mktemp -d)
PACKAGE_DIR="${TEMP_DIR}/${RELEASE_NAME}"

# Create package directory structure
mkdir -p "${PACKAGE_DIR}"

# Copy all files except excluded ones
rsync -av \
    --exclude='.git*' \
    --exclude='.context' \
    --exclude='.DS_Store' \
    --exclude='*.zip' \
    --exclude='build-release.sh' \
    --exclude='composer.lock' \
    ./ "${PACKAGE_DIR}/"

# Create zip from the parent directory
cd "${TEMP_DIR}"
zip -r "${RELEASE_FILE}" "${RELEASE_NAME}" > /dev/null

# Move zip back to original directory
mv "${RELEASE_FILE}" "${OLDPWD}/"

# Clean up temp directory
cd "${OLDPWD}"
rm -rf "${TEMP_DIR}"

# Get file size
FILE_SIZE=$(du -h "${RELEASE_FILE}" | cut -f1)

echo -e "${GREEN}✓ Release package created: ${RELEASE_FILE} (${FILE_SIZE})${NC}"
echo ""
echo -e "${GREEN}═══════════════════════════════════════${NC}"
echo -e "${GREEN}Build completed successfully!${NC}"
echo -e "${GREEN}═══════════════════════════════════════${NC}"
echo ""
echo -e "Package: ${GREEN}${RELEASE_FILE}${NC}"
echo -e "Version: ${GREEN}${VERSION}${NC}"
echo -e "Size:    ${GREEN}${FILE_SIZE}${NC}"
echo ""
echo "To deploy:"
echo "  1. Copy ${RELEASE_FILE} to your server"
echo "  2. Unzip in the CiviCRM extensions directory"
echo "  3. Enable the extension in CiviCRM"
echo ""
