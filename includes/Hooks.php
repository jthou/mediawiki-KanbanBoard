<?php
/**
 * MediaWiki Kanban Board Extension
 * 
 * @file
 * @ingroup Extensions
 * @author Your Name
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\KanbanBoard\KanbanBoard;
use MediaWiki\Extension\KanbanBoard\KanbanColumn;
use MediaWiki\Extension\KanbanBoard\KanbanCard;

class Hooks {
    
    /**
     * Hook handler for ParserFirstCallInit
     * 注册看板解析器标签
     */
    public static function onParserFirstCallInit( $parser ) {
        $parser->setHook( 'kanban', [ self::class, 'renderKanbanTag' ] );
        return true;
    }
    
    /**
     * 渲染看板标签
     */
    public static function renderKanbanTag( $input, $args, $parser, $frame ) {
        $parser->getOutput()->addModules( [ 'ext.kanbanboard' ] );
        
        $boardId = $args['board'] ?? 'default';
        $readOnly = isset( $args['readonly'] ) ? 'true' : 'false';
        
        $html = '<div class="kanban-board" data-board-id="' . htmlspecialchars( $boardId ) . '" data-readonly="' . $readOnly . '">';
        $html .= '<div class="kanban-loading">' . wfMessage( 'kanbanboard-loading' )->text() . '</div>';
        $html .= '</div>';
        
        return $html;
    }
}
