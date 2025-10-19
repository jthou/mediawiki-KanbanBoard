<?php
/**
 * Kanban Content Class
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class KanbanContent extends TextContent {
    
    public function __construct( $text ) {
        parent::__construct( $text, 'kanban-board' );
    }
    
    /**
     * 获取看板数据
     */
    public function getKanbanData() {
        // 这里可以根据需要实现看板数据的获取逻辑
        return null;
    }
    
    /**
     * 获取看板HTML
     */
    public function getKanbanHTML() {
        $text = $this->getText();
        
        if ( empty( $text ) ) {
            return '<div class="kanban-content-empty">看板内容为空</div>';
        }
        
        // 解析看板内容并生成HTML
        return $this->parseKanbanContent( $text );
    }
    
    /**
     * 解析看板内容
     */
    private function parseKanbanContent( $text ) {
        // 简单的看板内容解析
        $lines = explode( "\n", $text );
        $html = '<div class="kanban-content">';
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            
            if ( strpos( $line, '#' ) === 0 ) {
                // 标题
                $level = strlen( $line ) - strlen( ltrim( $line, '#' ) );
                $title = trim( substr( $line, $level ) );
                $html .= '<h' . min( $level, 6 ) . '>' . htmlspecialchars( $title ) . '</h' . min( $level, 6 ) . '>';
            } elseif ( strpos( $line, '- ' ) === 0 ) {
                // 列表项
                $item = trim( substr( $line, 2 ) );
                $html .= '<li>' . htmlspecialchars( $item ) . '</li>';
            } else {
                // 普通段落
                $html .= '<p>' . htmlspecialchars( $line ) . '</p>';
            }
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 获取文本摘要
     */
    public function getTextForSummary( $maxLength = 250 ) {
        $text = $this->getText();
        
        if ( strlen( $text ) <= $maxLength ) {
            return $text;
        }
        
        return substr( $text, 0, $maxLength ) . '...';
    }
    
    /**
     * 检查内容是否为空
     */
    public function isEmpty() {
        return empty( trim( $this->getText() ) );
    }
    
    /**
     * 获取内容大小
     */
    public function getSize() {
        return strlen( $this->getText() );
    }
    
    /**
     * 获取内容哈希
     */
    public function getHash() {
        return md5( $this->getText() );
    }
}
