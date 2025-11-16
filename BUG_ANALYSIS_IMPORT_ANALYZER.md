# Bug Analysis: Import Analysis Missing Contact and Energy Detection

## Summary

The `CustomerImportAnalyzer` class has a critical bug where it only analyzes **Customer** entities but completely ignores **Contact** and **Energy** entities that would be created during a FULL import.

## Reported Issue

When analyzing an Excel import file containing 3 rows with existing customers (matched by SIRET), the analysis incorrectly reports:
- Customer: 2 new, 1 update, 0 errors

But it SHOULD report:
- Customer: 0 new, 3 updates (all exist by SIRET)
- Contact: 3 to create
- Energy: 3 to create

## Root Cause Analysis

### Location
File: `/home/james/projets/crm-gdb-consulting/src/Domain/Import/Service/Analyzer/CustomerImportAnalyzer.php`

### The Bug

In the `analyzeRow` method (lines 214-260), the analyzer:

1. **Only analyzes Customer existence** (line 237):
   ```php
   $operationType = $this->determineOperationType($rowData);
   ```

2. **Only tracks Customer operations** (lines 239-245):
   ```php
   $entityType = Customer::class;
   match ($operationType) {
       ImportOperationType::CREATE => $this->creations[$entityType] = ($this->creations[$entityType] ?? 0) + 1,
       ImportOperationType::UPDATE => $this->updates[$entityType] = ($this->updates[$entityType] ?? 0) + 1,
       ImportOperationType::SKIP => $this->skips[$entityType] = ($this->skips[$entityType] ?? 0) + 1,
   };
   ```

3. **Never analyzes Contact or Energy data** - There is no code that:
   - Checks if contact data exists in the row (firstname, lastname, email, phone)
   - Checks if energy data exists in the row (provider, PDL/PCE, energy type, contract end date)
   - Tracks Contact or Energy creation counts

### What's Missing

The analyzer should:

1. **Detect Contact data** in each row:
   - Check for `contact_firstname`, `contact_lastname`, `email`, `phone`
   - Count how many contacts will be created
   - For updates: Check if contact already exists or is new

2. **Detect Energy data** in each row:
   - Check for `provider`, `pce_pdl`, `energy_type`, `contract_end`
   - Count how many energies will be created
   - For updates: Check if energy already exists or is new

3. **Track all entities** in separate counters:
   ```php
   $this->creations[Customer::class] = ...
   $this->creations[Contact::class] = ...
   $this->creations[Energy::class] = ...
   ```

## Test Coverage

### Functional Tests Created
File: `/home/james/projets/crm-gdb-consulting/tests/Functional/Import/ImportAnalysisTest.php`

**Test Case 1: testAnalysisWithAllExistingCustomersBySiret**
- Creates 3 customers with SIRETs matching the example file
- Analyzes the import
- **Expected**: 3 customer updates, 3 contact creates, 3 energy creates
- **Actual**: Only shows customer analysis, Contact and Energy are missing (BUG)

**Test Case 2: testAnalysisWithMixedCustomers**
- Creates 2 existing customers, 1 new
- **Expected**: 1 customer create, 2 customer updates, 3 contact creates, 3 energy creates

**Test Case 3: testAnalysisWithAllNewCustomers**
- No existing customers
- **Expected**: 3 customer creates, 3 contact creates, 3 energy creates

**Test Case 4: testCustomerMatchingBySiretNotByName**
- Verifies that SIRET matching takes precedence over name matching

**Test Case 5: testAnalysisWithExistingCustomersAndRelatedEntities**
- Tests customers that already have contacts and energies
- New contacts and energies should still be detected

### Unit Tests Created
File: `/home/james/projets/crm-gdb-consulting/tests/Unit/Domain/Import/Service/Analyzer/CustomerImportAnalyzerTest.php`

Tests cover:
- Import type support (CUSTOMER and FULL)
- SIRET-based customer matching
- Name-based fallback matching
- Required field validation
- Empty row handling
- Batch processing

All 9 unit tests PASS - they test the existing Customer analysis logic.

## Test Execution Results

```bash
# Unit tests - ALL PASS
docker compose exec php bin/phpunit tests/Unit/Domain/Import/Service/Analyzer/CustomerImportAnalyzerTest.php
# Result: 9 tests, 19 assertions, all passed

# Functional test - FAILS (reproduces bug)
docker compose exec php bin/phpunit tests/Functional/Import/ImportAnalysisTest.php --filter testAnalysisWithAllExistingCustomersBySiret
# Result: FAILS at assertion "Analysis should include Contact entity"
# Error: "Failed asserting that an array has the key 'App\Entity\Contact'"
```

## Impact

This bug affects:
1. **Analysis accuracy**: Users don't see the full impact of their import
2. **User expectations**: The analysis doesn't match what actually gets imported
3. **Decision making**: Users can't properly evaluate what will be created/updated
4. **Data validation**: Potential contact/energy data issues aren't detected during analysis

## Proposed Fix Strategy

The fix should add analysis for Contact and Energy entities:

### 1. Add helper methods to detect entity data:

```php
private function hasContactData(array $rowData): bool
{
    return !empty($rowData['contact_firstname'])
        || !empty($rowData['contact_lastname'])
        || !empty($rowData['email']);
}

private function hasEnergyData(array $rowData): bool
{
    return !empty($rowData['pce_pdl'])
        || !empty($rowData['provider'])
        || !empty($rowData['energy_type']);
}
```

### 2. Extend analyzeRow to track all entities:

```php
private function analyzeRow(Import $import, array $row, int $rowNumber): void
{
    // ... existing customer analysis ...

    // Analyze Contact if data present
    if ($this->hasContactData($rowData)) {
        $this->creations[Contact::class] = ($this->creations[Contact::class] ?? 0) + 1;
    }

    // Analyze Energy if data present
    if ($this->hasEnergyData($rowData)) {
        $this->creations[Energy::class] = ($this->creations[Energy::class] ?? 0) + 1;
    }
}
```

### 3. Consider more sophisticated analysis:

For a complete solution, the analyzer should also:
- Check if contacts already exist (by email + customer)
- Check if energies already exist (by code + type + customer)
- Mark them as UPDATE vs CREATE accordingly

## Files Modified

1. Created: `/home/james/projets/crm-gdb-consulting/tests/Functional/Import/ImportAnalysisTest.php`
   - 5 comprehensive functional test cases
   - 438 lines of test code

2. Created: `/home/james/projets/crm-gdb-consulting/tests/Unit/Domain/Import/Service/Analyzer/CustomerImportAnalyzerTest.php`
   - 9 unit test cases
   - 420 lines of test code

3. Created: `/home/james/projets/crm-gdb-consulting/BUG_ANALYSIS_IMPORT_ANALYZER.md`
   - This comprehensive analysis document

## Next Steps

1. Review and approve the proposed fix strategy
2. Implement the fix in `CustomerImportAnalyzer.php`
3. Run all tests to verify the fix:
   ```bash
   docker compose exec php bin/phpunit tests/Functional/Import/ImportAnalysisTest.php
   docker compose exec php bin/phpunit tests/Unit/Domain/Import/Service/Analyzer/CustomerImportAnalyzerTest.php
   ```
4. Run GrumPHP to ensure code quality:
   ```bash
   docker compose exec php vendor/bin/grumphp run
   ```

## Commands to Run Tests

```bash
# Run all analysis tests
docker compose exec php bin/phpunit tests/Functional/Import/ImportAnalysisTest.php --testdox

# Run specific test reproducing the bug
docker compose exec php bin/phpunit tests/Functional/Import/ImportAnalysisTest.php --filter testAnalysisWithAllExistingCustomersBySiret --testdox

# Run unit tests
docker compose exec php bin/phpunit tests/Unit/Domain/Import/Service/Analyzer/CustomerImportAnalyzerTest.php --testdox

# Run all tests
docker compose exec php bin/phpunit

# Check code quality
docker compose exec php vendor/bin/grumphp run
```
