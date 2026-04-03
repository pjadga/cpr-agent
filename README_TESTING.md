# Testing Documentation

This document describes the comprehensive testing suite for the Cyberpunk Agent application.

## Overview

The testing suite is organized into several categories:
- **Backend Tests**: Tests for Foundry VTT callback API integration
- **Frontend Tests**: Tests for authentication, API connections, and Laravel framework features
- **Unit Tests**: Tests for individual components and helper methods
- **Integration Tests**: End-to-end workflow tests

## Test Suites

### Backend Suite (`tests/Feature/Api/`)

Tests all Foundry VTT integration points and callback handling.

#### FoundryCallbackTest.php
Comprehensive tests for the `/api/foundry/callback` endpoint covering:
- Authentication requirements
- Character linking (player and GM-initiated)
- Actor data updates (full and delta)
- Roll results (attack, damage, death saves, initiative, etc.)
- Item management (creation, updates, deletion)
- Cyberdeck and program operations
- Broadcasting and caching
- Request validation

**Key Test Cases:**
- `it_requires_authentication()` - Ensures API is protected
- `it_handles_link_player_character_request()` - Player character linking
- `it_handles_gm_link_player_character_request()` - GM-initiated linking
- `it_handles_actor_data_update()` - Full character sheet sync
- `it_handles_sheet_delta_updates()` - Incremental updates
- `it_handles_roll_result_callback()` - Dice roll results
- `it_handles_item_creation_delta()` - Adding items to inventory
- `it_handles_item_deletion_delta()` - Removing items
- `it_handles_item_update_delta()` - Modifying item properties
- `it_caches_response_for_request_id()` - Response caching
- `it_broadcasts_with_channel_id()` - Channel-based broadcasting

#### FoundryIntegrationTest.php
End-to-end workflow tests simulating real Foundry VTT scenarios:
- Complete character linking workflow
- Combat sequences (initiative, attack, damage, ammo tracking)
- Netrunning workflows (program installation, rezzing, attacks)
- Death save sequences
- GM character updates
- Multi-player scenarios
- Item equipping
- Face down rolls
- Stat and skill rolls

**Key Test Cases:**
- `complete_character_link_workflow()` - Full character setup
- `complete_combat_workflow()` - Initiative → Attack → Damage → Ammo
- `complete_netrunning_workflow()` - Install → Rez → Attack
- `complete_death_save_workflow()` - Death save tracking
- `gm_can_update_player_character()` - GM permissions
- `multiple_players_in_same_channel()` - Multi-user support

### Frontend Suite (`tests/Feature/Frontend/`)

Tests for frontend authentication and API connectivity.

#### AuthenticationFlowTest.php
Laravel Breeze authentication tests:
- Registration and login flows
- Password management
- Profile updates
- Account deletion
- User model attributes (agent_link, channel_id)
- Dashboard access control

**Key Test Cases:**
- `new_users_can_register()` - User registration
- `users_can_authenticate_using_the_login_screen()` - Login
- `users_can_logout()` - Logout
- `user_has_agent_link_on_creation()` - Auto-generated agent link
- `user_has_channel_id_on_creation()` - Auto-generated channel ID
- `user_can_update_profile_information()` - Profile editing
- `user_can_update_password()` - Password changes

#### ApiConnectionTest.php
Frontend-to-backend API communication tests:
- Sanctum token authentication
- API endpoint accessibility
- Error handling and validation
- Large data handling
- Concurrent requests
- Special character handling
- CORS and headers

**Key Test Cases:**
- `frontend_can_authenticate_with_sanctum_token()` - Token auth
- `frontend_can_send_foundry_callback()` - API calls
- `frontend_receives_validation_errors()` - Error responses
- `frontend_cannot_access_api_without_authentication()` - Security
- `frontend_can_handle_large_character_data()` - Performance
- `frontend_can_send_concurrent_requests()` - Concurrency

### Broadcasting Tests (`tests/Feature/BroadcastingTest.php`)

Tests for Laravel Reverb/Broadcasting integration:
- Event dispatching
- Channel routing
- Authentication
- Payload integrity
- Multi-event handling

**Key Test Cases:**
- `foundry_response_event_can_be_dispatched()` - Event system
- `foundry_response_broadcasts_on_correct_channel()` - Channel routing
- `broadcasting_auth_requires_authentication()` - Security
- `event_payload_includes_all_required_fields()` - Data integrity

### Unit Tests (`tests/Unit/`)

#### FoundryCallbackHelperTest.php
Tests for the `arraySetDot()` helper method:
- Dot notation path parsing
- Nested updates
- Item array operations (add, update, delete)
- Null value handling (deletion)
- Deep nesting
- Data type handling (numeric, boolean, array)

**Key Test Cases:**
- `it_can_parse_dot_notation_paths()` - Path parsing
- `it_can_add_items_to_array()` - Item creation
- `it_can_update_existing_item_by_id()` - Item updates
- `it_can_delete_item_by_id()` - Item deletion
- `it_prevents_duplicate_items_when_adding()` - Duplicate prevention

#### UserModelTest.php
Tests for the User model:
- Agent link generation
- Channel ID generation
- Character data storage
- Sanctum token management
- Model attributes and casting
- Fillable fields

**Key Test Cases:**
- `user_generates_agent_link_on_creation()` - UUID generation
- `user_generates_channel_id_on_creation()` - Channel ID format
- `user_character_data_is_cast_to_array()` - Data casting
- `user_can_create_sanctum_tokens()` - Token creation
- `generate_channel_id_creates_valid_format()` - ID validation

## Running Tests

### Run All Tests
```bash
php artisan test
```

### Run Specific Test Suites
```bash
# Backend tests only
php artisan test --testsuite=Backend

# Frontend tests only
php artisan test --testsuite=Frontend

# Unit tests only
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature
```

### Run Specific Test Files
```bash
# Foundry callback tests
php artisan test tests/Feature/Api/FoundryCallbackTest.php

# Integration tests
php artisan test tests/Feature/Api/FoundryIntegrationTest.php

# Authentication tests
php artisan test tests/Feature/Frontend/AuthenticationFlowTest.php

# API connection tests
php artisan test tests/Feature/Frontend/ApiConnectionTest.php
```

### Run Specific Test Methods
```bash
php artisan test --filter=it_handles_roll_result_callback
```

### Run with Coverage (requires Xdebug or PCOV)
```bash
php artisan test --coverage
```

### Run in Parallel (faster)
```bash
php artisan test --parallel
```

## Test Database

Tests use an in-memory SQLite database configured in `phpunit.xml`:
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

This ensures:
- Fast test execution
- No pollution of development database
- Clean state for each test run

## Continuous Integration

The test suite is designed to run in CI/CD pipelines. Example GitHub Actions workflow:

```yaml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
      - name: Install Dependencies
        run: composer install
      - name: Run Tests
        run: php artisan test
```

## Test Coverage Goals

- **Backend API**: 100% coverage of Foundry callback types
- **Frontend Auth**: All Laravel Breeze flows
- **Integration**: All major user workflows
- **Unit**: Critical helper methods and models

## Writing New Tests

### Backend Tests
When adding new Foundry callback types:
1. Add test to `FoundryCallbackTest.php` for the specific callback
2. Add integration test to `FoundryIntegrationTest.php` for the workflow
3. Ensure event broadcasting is tested

### Frontend Tests
When adding new frontend features:
1. Add authentication tests if auth-related
2. Add API connection tests if calling backend
3. Test both success and error cases

### Unit Tests
When adding helper methods or model methods:
1. Test all code paths
2. Test edge cases (null, empty, invalid data)
3. Test data type handling

## Common Test Patterns

### Testing API Endpoints
```php
$response = $this->withToken($this->token)
    ->postJson('/api/foundry/callback', [
        'type' => 'ROLL_RESULT',
        'total' => 15,
    ]);

$response->assertStatus(200);
```

### Testing Events
```php
Event::fake([FoundryResponse::class]);

// ... trigger event ...

Event::assertDispatched(FoundryResponse::class, function ($event) {
    return $event->payload['type'] === 'ROLL_RESULT';
});
```

### Testing Database Changes
```php
$this->user->refresh();
$this->assertEquals(40, $this->user->character_data['system']['hp']['value']);
```

### Testing Authentication
```php
$this->actingAs($user)->get('/dashboard')->assertStatus(200);
```

## Troubleshooting

### Tests Failing Due to Database
- Ensure migrations are up to date
- Check `phpunit.xml` database configuration
- Clear cache: `php artisan config:clear`

### Event Broadcasting Tests Failing
- Ensure `Event::fake()` is called before triggering events
- Check event class imports

### Authentication Tests Failing
- Verify Laravel Breeze is installed
- Check routes are registered
- Ensure middleware is configured

## Additional Resources

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Laravel Sanctum Testing](https://laravel.com/docs/sanctum#testing)
- [Laravel Broadcasting Testing](https://laravel.com/docs/broadcasting#testing)
