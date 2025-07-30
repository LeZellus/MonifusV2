# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Development
- `composer install` - Install PHP dependencies
- `php bin/console` - Symfony console for all CLI commands
- `php bin/console doctrine:database:create` - Create database
- `php bin/console doctrine:migrations:migrate` - Run database migrations
- `php bin/console doctrine:fixtures:load` - Load sample data

### Asset Management
- `php bin/console tailwind:build` - Build Tailwind CSS
- `php bin/console tailwind:build --watch` - Watch for changes and rebuild CSS
- `php bin/console asset-map:compile` - Compile asset map for production

### Testing
- `vendor/bin/phpunit` or `bin/phpunit` - Run PHPUnit tests
- `php bin/console doctrine:fixtures:load --env=test` - Load test fixtures

### Custom Commands
- `php bin/console app:fetch-dofus-items` - Fetch items from Dofus API
- `php bin/console app:fix-item-types` - Fix item type data

## Architecture

### Core Domain
Monifus is a Dofus trading application that helps players manage their item lots, track profits, and monitor market prices. The application centers around:

- **LotGroup**: Main trading entity representing a batch of items with buy/sell prices
- **Item**: Dofus game items fetched from external API
- **DofusCharacter**: Player characters that own lots
- **MarketWatch**: Price monitoring for specific items

### Key Services
- **DofusApiService**: Interfaces with external Dofus API (api.beta.dofusdb.fr)
- **TradingCalculatorService**: Calculates profits, taxes, and trading metrics
- **ProfitCalculatorService**: Handles profit calculations with tax considerations
- **ChartDataService**: Generates data for analytics charts
- **BackupService**: Handles data import/export functionality

### Database Schema
Uses Doctrine ORM with migrations. Key relationships:
- User → DofusCharacter (one-to-many)
- DofusCharacter → LotGroup (one-to-many) 
- Item → LotGroup (one-to-many)
- Item → MarketWatch (one-to-many)

### Frontend Stack
- Symfony UX with Stimulus controllers
- Tailwind CSS for styling
- Chart.js for analytics visualization
- Twig templates with component-based architecture

### Authentication
Uses Discord OAuth2 via KnpU OAuth2 Client Bundle for user authentication.

### File Structure
- `src/Entity/` - Doctrine entities
- `src/Repository/` - Database repositories
- `src/Service/` - Business logic services
- `src/Controller/` - HTTP controllers
- `src/Form/` - Symfony forms
- `src/Enum/` - PHP enums for constants
- `templates/` - Twig templates
- `assets/` - Frontend assets (JS/CSS)
- `migrations/` - Database migrations

### Development Notes
- Uses PHP 8.2+ with Symfony 7.3
- Implements Tailwind safelist for dynamic color classes
- Custom Twig extensions for formatting (Kamas, Profit calculations)
- Stimulus controllers handle interactive UI components
- Database uses bigint for price fields to handle large kama amounts