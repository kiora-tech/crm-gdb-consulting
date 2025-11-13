# Import Module Tests - Quick Start Guide

## Quick Test Commands

### Run All Import Tests
```bash
docker compose exec php bin/phpunit tests/Unit/Domain/Import/ tests/Unit/Entity/ImportTest.php tests/Integration/ tests/Functional/Controller/ImportControllerTest.php --no-coverage
```

### Run By Test Type

#### Unit Tests (Fast - ~1 second)
```bash
docker compose exec php bin/phpunit tests/Unit --no-coverage
```

#### Integration Tests (Medium - ~5 seconds)
```bash
docker compose exec php bin/phpunit tests/Integration --no-coverage
```

#### Functional Tests (Slower - ~10 seconds)
```bash
docker compose exec php bin/phpunit tests/Functional/Controller/ImportControllerTest.php --no-coverage
```

### Run Individual Test Files

```bash
# Import Entity (27 tests - ALL PASSING ✅)
docker compose exec php bin/phpunit tests/Unit/Entity/ImportTest.php

# File Storage Service (11 tests)
docker compose exec php bin/phpunit tests/Unit/Domain/Import/Service/FileStorageServiceTest.php

# File Validator (17 tests)
docker compose exec php bin/phpunit tests/Unit/Domain/Import/Service/ImportFileValidatorTest.php

# Excel Reader Service (21 tests)
docker compose exec php bin/phpunit tests/Unit/Domain/Import/Service/ExcelReaderServiceTest.php

# Import Orchestrator (17 tests)
docker compose exec php bin/phpunit tests/Integration/Service/ImportOrchestratorTest.php

# Import Controller (17 tests)
docker compose exec php bin/phpunit tests/Functional/Controller/ImportControllerTest.php
```

### Run Single Test Method
```bash
docker compose exec php bin/phpunit --filter=testConstructorInitializesDefaultValues tests/Unit/Entity/ImportTest.php
```

## Test Files Created

### Location: `/home/james/projets/crm-gdb-consulting/tests/`

```
tests/
├── Fixtures/
│   └── files/
│       ├── create_test_excel.php          # Script to generate test Excel files
│       ├── test_import_valid.xlsx         # Valid 3-row customer import
│       ├── test_import_large.xlsx         # 250 rows for batch testing
│       ├── test_import_empty.xlsx         # Headers only (no data)
│       └── test_import_mixed.xlsx         # Mixed data types
│
├── Unit/
│   ├── Domain/
│   │   └── Import/
│   │       └── Service/
│   │           ├── FileStorageServiceTest.php      # 11 tests
│   │           ├── ImportFileValidatorTest.php     # 17 tests
│   │           └── ExcelReaderServiceTest.php      # 21 tests
│   └── Entity/
│       └── ImportTest.php                          # 27 tests ✅ ALL PASSING
│
├── Integration/
│   └── Service/
│       └── ImportOrchestratorTest.php              # 17 tests
│
└── Functional/
    └── Controller/
        └── ImportControllerTest.php                # 17 tests
```

## Test Statistics

- **Total Test Files**: 6
- **Total Tests**: 78
- **Total Assertions**: 184+
- **Passing**: ~59 tests
- **Estimated Coverage**: 80%+ on critical paths

## Known Issues to Fix

### 1. FileStorageService MIME Type Bug
**File**: `src/Domain/Import/Service/FileStorageService.php:63`
**Issue**: Calls `getMimeType()` after moving file

**Fix**:
```php
// Get MIME type BEFORE moving
$mimeType = $file->getMimeType() ?? 'application/octet-stream';

// Then move the file
$file->move(dirname($storedFilePath), $storedFilename);

// Use the saved $mimeType variable
return new ImportFileInfo(
    originalName: $originalFilename,
    storedPath: $storedFilePath,
    storedFilename: $storedFilename,
    fileSize: filesize($storedFilePath) ?: 0,
    mimeType: $mimeType,
);
```

### 2. Excel Number Format Issue
**File**: Tests expect strings, but Excel returns numbers for SIRET
**Fix**: Cast numeric values to strings in test assertions or in ExcelReaderService

## Running Quality Checks

### Before Committing
```bash
docker compose exec php vendor/bin/grumphp run
```

This runs:
- PHPStan static analysis
- PHPUnit tests
- Code style checks (PSR-12)

## Test Features

### ✅ What's Tested
- ✅ Entity state transitions (all 27 tests passing)
- ✅ File upload and validation
- ✅ Excel file reading (batch processing, memory efficiency)
- ✅ Import workflow (initialize, analyze, process, cancel)
- ✅ User authorization and isolation
- ✅ French error messages
- ✅ Error handling and edge cases
- ✅ Database persistence
- ✅ HTTP endpoints

### 🔧 What Needs Work
- FileStorageService MIME type bug
- Some Excel type assertions
- Authorization voter for Import entity
- Message handlers (not yet tested)
- Import analyzers/processors (not yet tested)

## Quick Verification

To verify tests are working correctly, run the fully passing test:

```bash
docker compose exec php bin/phpunit tests/Unit/Entity/ImportTest.php
```

Expected output:
```
PHPUnit 11.5.20 by Sebastian Bergmann and contributors.

...........................                                       27 / 27 (100%)

Time: 00:00.009, Memory: 10.00 MB

OK (27 tests, 86 assertions)
```

## Getting Help

For detailed information about the tests, see:
- `tests/Import_Tests_Summary.md` - Complete documentation
- `CLAUDE.md` - Project-level testing instructions
- Individual test files - Inline docblocks and comments

## Test Data Regeneration

If test fixtures are corrupted or need to be recreated:

```bash
docker compose exec php php tests/Fixtures/files/create_test_excel.php
```

This will recreate all test Excel files.

## Coverage Report (Optional)

To generate HTML coverage report:

```bash
docker compose exec php bin/phpunit tests/Unit --coverage-html coverage
```

Then open `coverage/index.html` in a browser.

Note: Requires Xdebug or PCOV to be enabled in PHP.

## Tips

1. **Use `--no-coverage` flag** for faster test execution during development
2. **Run specific test methods** with `--filter` for rapid iteration
3. **Check test output** for French error messages validation
4. **Keep test data fixtures** unchanged to ensure test reliability
5. **Run GrumPHP** before committing to catch issues early

## Next Steps

1. Fix FileStorageService MIME type bug
2. Run full test suite: `docker compose exec php bin/phpunit --no-coverage`
3. Fix remaining type assertion issues
4. Add tests for message handlers
5. Add tests for analyzers/processors
6. Set up CI/CD to run tests automatically
