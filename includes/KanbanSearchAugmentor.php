<?php
/**
 * Kanban Search Augmentor
 * 
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\KanbanBoard;

use MediaWiki\Search\ResultSetAugmentor;
use MediaWiki\Search\ISearchResultSet;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

class KanbanSearchAugmentor implements ResultSetAugmentor {
    
    /**
     * 增强搜索结果
     */
    public function augmentAll( ISearchResultSet $resultSet ) {
        // 获取搜索词和用户信息
        $request = \RequestContext::getMain()->getRequest();
        $term = $request->getText( 'search' );
        $user = \RequestContext::getMain()->getUser();
        
        if ( empty( $term ) ) {
            return [];
        }
        
        // 获取看板任务搜索结果
        $kanbanResults = SearchHooks::searchKanbanTasks( $term, $user );
        
        if ( empty( $kanbanResults ) ) {
            return [];
        }
        
        // 将看板任务结果添加到搜索结果中
        foreach ( $kanbanResults as $result ) {
            $resultSet->addInterwikiResults( $result, \SearchResultSet::SECONDARY_RESULTS, 'kanban' );
        }
        
        return [
            'count' => count( $kanbanResults ),
            'results' => $kanbanResults
        ];
    }
}
