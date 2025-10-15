<?php
/**
 * Minimal Kanban Board API Module
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use ApiBase;

class ApiKanban extends ApiBase {
    
    public function execute() {
        $this->getResult()->addValue( null, 'test', 'API is working' );
        $this->getResult()->addValue( null, 'timestamp', time() );
    }
    
    public function getAllowedParams() {
        return [];
    }
    
    public function mustBePosted() {
        return false;
    }
    
    public function isWriteMode() {
        return false;
    }
    
    public function needsToken() {
        return false;
    }
    
    public function isReadMode() {
        return true;
    }
}