# MediaWiki Kanban Board Extension

A MediaWiki extension that adds Kanban board functionality to MediaWiki pages.

## Features

- Create and manage Kanban boards
- Add columns and cards to boards
- Drag and drop functionality (planned)
- User permissions and access control
- Multi-language support (English and Chinese)

## Installation

1. Clone or download this extension to your MediaWiki `extensions/` directory
2. Add the following to your `LocalSettings.php`:

```php
wfLoadExtension( 'KanbanBoard' );
```

3. Run the database update script to create the necessary tables:

```bash
php maintenance/update.php
```

## Usage

### Creating a Kanban Board

Visit `Special:KanbanBoard` to create and manage boards.

### Embedding a Kanban Board in a Page

Use the following syntax in any wiki page:

```wiki
<kanban board="1" />
```

Replace `1` with the ID of your board.

## Database Schema

The extension creates the following tables:

- `kanban_boards` - Stores board information
- `kanban_columns` - Stores column information for each board
- `kanban_cards` - Stores card information for each column
- `kanban_permissions` - Stores user permissions for boards
- `kanban_comments` - Stores comments on cards
- `kanban_attachments` - Stores file attachments on cards

## API

The extension provides a REST API for managing boards:

- `GET /api.php?action=kanban&kanban_action=getboard&board_id=1` - Get board data
- `POST /api.php?action=kanban&kanban_action=createboard` - Create a new board
- `POST /api.php?action=kanban&kanban_action=addcard` - Add a card to a column
- `POST /api.php?action=kanban&kanban_action=movecard` - Move a card between columns

## Configuration

You can configure the extension in `LocalSettings.php`:

```php
// Maximum number of columns per board
$wgKanbanBoardMaxColumns = 10;

// Maximum number of cards per column
$wgKanbanBoardMaxCardsPerColumn = 100;

// Allow anonymous users to edit boards
$wgKanbanBoardAllowAnonymousEdit = false;
```

## Development

### File Structure

```
KanbanBoard/
├── extension.json          # Extension metadata
├── includes/              # PHP classes
│   ├── ApiKanban.php      # API module
│   ├── Hooks.php          # Hook handlers
│   ├── KanbanBoard.php    # Board entity
│   ├── KanbanColumn.php   # Column entity
│   ├── KanbanCard.php     # Card entity
│   └── SpecialKanbanBoard.php # Special page
├── resources/             # Frontend assets
│   ├── css/kanban.css     # Styles
│   └── js/kanban.js       # JavaScript
├── sql/                   # Database schema
│   └── kanban_tables.sql  # Table definitions
└── i18n/                  # Internationalization
    ├── en.json            # English messages
    └── zh-cn.json         # Chinese messages
```

## License

This extension is licensed under the GNU General Public License v2 or later.

## Author

Created for MediaWiki project management and task tracking.
