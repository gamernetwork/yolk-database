<?php
/*
 * This file is part of Yolk - Gamer Network's PHP Framework.
 *
 * Copyright (c) 2013 Gamer Network Ltd.
 *
 * Distributed under the MIT License, a copy of which is available in the
 * LICENSE file that was bundled with this package, or online at:
 * https://github.com/gamernetwork/yolk
 */

namespace yolk\database\support;

use yolk\contracts\database\DatabaseConnection;
use yolk\contracts\support\Tree;

// only manipulates tree fields (lft, rgt), doesn't insert or delete database records
class DatabaseTree implements Tree {

	protected $db;

	protected $table_name;
	protected $name_field;

	public function __construct( DatabaseConnection $db, $table_name, $name_field = 'name' ) {
		$this->db         = $db;
		$this->table_name = $table_name;
		$this->name_field = $name_field;
	}

	public function countAncestors( $id ) {

		$node = $this->findNodeById($id);

		return $node ? (int) $this->db->getOne(
			"SELECT COUNT(*)
			  FROM {$this->table_name}
			 WHERE lft < :lft
			   AND rgt > :rgt",
			$node
		) : 0;

	}

	public function getAncestors( $id ) {

		$node = $this->findNodeById($id);

		return $node ? $this->db->getAssoc(
			"SELECT id, {$this->name_field}
			   FROM {$this->table_name}
			  WHERE lft < :lft
			    AND rgt > :rgt
		   ORDER BY lft ASC",
			$node
		) : array();

	}

	public function countSiblings( $id ) {
		return (int) $this->db->getOne("SELECT COUNT(*) FROM {$this->table_name} WHERE parent_id = (SELECT parent_id FROM {$this->table_name} WHERE id = ?)", $id) - 1;
	}

	public function getSiblings( $id ) {
		$siblings = $this->db->getAssoc("SELECT id, {$this->name_field} FROM {$this->table_name} WHERE parent_id = (SELECT parent_id FROM {$this->table_name} WHERE id = ?)", $id);
		asort($siblings);
		return $siblings;
	}

	public function countChildren( $id ) {
		return (int) $this->db->getOne("SELECT COUNT(*) FROM {$this->table_name} WHERE parent_id = ", $id);
	}

	public function getChildren( $id ) {
		$children = $this->db->getAssoc("SELECT id, {$this->name_field} FROM {$this->table_name} WHERE parent_id = ?", $id);
		asort($children);
		return $children;
	}

	public function countDescendants( $id ) {
		return (int) $this->db->getOne("SELECT (rgt - lft - 1) / 2 FROM {$this->table_name} WHERE id = ?", $id);
	}

	public function getDescendants( $id, $absolute_depth = false ) {

		$node = $this->findNodeById($id);

		if( !$node )
			return array();

		$descendants = $this->db->getAssoc(
			"SELECT id,
				   {$this->name_field},
				   lft,
				   rgt
			  FROM {$this->table_name}
			 WHERE lft BETWEEN :lft + 1 AND :rgt - 1
		  ORDER BY lft ASC",
			$node
		);

		$offset = $absolute_depth ? $this->countAncestors($id) : 0;

		$stack = array();
		foreach( $descendants as &$child ) {

			$child['lft'] = (int) $child['lft'];
			$child['rgt'] = (int) $child['rgt'];

			// if current left > right on top of stack we've gone down a level so pop the stack
			while( $stack && $child['lft'] > $stack[count($stack) - 1] )
				array_pop($stack);

			$child['depth'] = $offset + count($stack) + 1;

			// node has children so push current right value to stack
			if( $child['rgt'] - $child['lft'] > 1 )
				$stack[] = $child['rgt'];

			unset($child['lft']);
			unset($child['rgt']);

		}

		return $descendants;

	}

	public function insertNode( $id, $parent_id = 0 ) {

		try {

			$this->db->begin();

			if( $parent_id ) {

				$parent = $this->findNodeById($parent_id);

				// shift everything up to make room for the new node
				$this->db->query("UPDATE {$this->table_name} SET rgt = rgt + 2 WHERE rgt >= ?", $parent['rgt']);
				$this->db->query("UPDATE {$this->table_name} SET lft = lft + 2 WHERE lft >= ?", $parent['rgt']);

				// single query - http://www.slideshare.net/billkarwin/models-for-hierarchical-data (slide 36)
				/*update table set
				lft = case when left >= parent_rgt then lft + 2 else lft end
				rgt = rgt + 2
				where rgt > parent_lft*/

			}
			else {
				$parent = array(
					'rgt' => $this->db->getOne("SELECT MAX(rgt) + 1 FROM {$this->table_name}")
				);
			}

			$this->db->execute(
				"UPDATE {$this->table_name} SET lft = ?, rgt = ? WHERE id = ?",
				array($parent['rgt'], $parent['rgt'] + 1, $id)
			);

			$this->db->commit();

		}
		catch( \Exception $e ) {
			$this->db->rollback();
			throw $e;
		}

		return $this;

	}

	public function removeNode( $id ) {

		if( !$node = $this->findNodeById($id) )
			return $this;

		try {

			$this->db->begin();

			$diff = (int) $node['rgt'] - (int) $node['lft'] + 1;

			// remove the item from the tree by blanking the left and right indexes
			$this->db->execute("UPDATE {$this->table_name} SET lft = 0, rgt = 0 WHERE lft BETWEEN ? AND ?", array($node['lft'], $node['rgt']));
			
			$this->db->execute("UPDATE {$this->table_name} SET lft = lft - ? WHERE lft >= ?", array($diff, $node['lft']));
			$this->db->execute("UPDATE {$this->table_name} SET rgt = rgt - ? WHERE rgt >= ?", array($diff, $node['rgt']));

			$this->db->commit();

		}
		catch( \Exception $e ) {
			$this->db->rollback();
			throw $e;
		}

		return $this;

	}

	public function moveNode( $id, $parent_id ) {

		$node   = $this->findNodeById($id);
		$parent = $this->findNodeById($parent_id);

		// both source and parent must exist
		if( !$node || !$parent )
			return $this;

		try {

			$this->db->begin();

			$diff = $node['rgt'] - $node['lft'] + 1;

			// remove the node and it's descendents from the tree whilst keeping it's structure, by converting the left and right values to negative numbers
			$this->db->execute(
				"UPDATE {$this->table_name} SET lft = -(lft - :lft + 1), rgt = -(rgt -:lft + 1) WHERE lft >= :lft AND rgt <= :rgt",
				array(
					'lft'  => $node['lft'],
					'rgt'  => $node['rgt']
				)
			);

			// collapse the gap we just created
			$this->db->execute("UPDATE {$this->table_name} SET lft = lft - ? WHERE lft > ?", array($diff, $node['lft']));
			$this->db->execute("UPDATE {$this->table_name} SET rgt = rgt - ? WHERE rgt > ?", array($diff, $node['rgt']));

			// refresh parent state
			$parent = $this->findNodeById($parent_id);

			// create new gap
			$this->db->execute("UPDATE {$this->table_name} SET lft = lft + ? WHERE lft > ?", array($diff, $parent['rgt']));
			$this->db->execute("UPDATE {$this->table_name} SET rgt = rgt + ? WHERE rgt >= ?", array($diff, $parent['rgt']));

			// refresh parent state
			$parent = $this->findNodeById($parent_id);

			// fill the gap with the original node, updating the left and right values
			$this->db->execute("UPDATE {$this->table_name} SET lft = ? - ? - lft - 1 WHERE lft < 0", array($parent['rgt'], $diff));
			$this->db->execute("UPDATE {$this->table_name} SET rgt = ? - ? - rgt - 1 WHERE rgt < 0", array($parent['rgt'], $diff));

			// ensure the correct parent is assigned - may have already by done by calling function
			$this->db->execute("UPDATE {$this->table_name} SET parent_id = ? WHERE id = ?", array($parent_id, $id));

			$this->db->commit();

		}
		catch( \Exception $e ) {
			$this->db->rollback();
			throw $e;
		}

		return $this;

	}

	public function getTree( $id, $max_depth = 0, $sort = false ) {

		$node = $this->findNodeById($id);

		if( !$node )
			return array();

		// fetch all the nodes in this sub-tree and calculate their depth
		$data = $this->db->getAssoc(
			"SELECT n.id, n.{$this->name_field}, COUNT(p.id) - 1 as depth
			   FROM {$this->table_name} AS n,
			        {$this->table_name} AS p
			  WHERE n.lft BETWEEN p.lft AND p.rgt
			    AND n.lft BETWEEN ? AND ?
		   GROUP BY n.id
		   ORDER BY n.lft",
			array(
				$node['lft'],
				$node['rgt']
			)
		);

		// as depth is relative to the requested node we need to take into account any ancestors
		$max_depth += $this->countAncestors($id);

		$tree = array();
		$path = array();
		$prev = 0;

		foreach( $data as $id => $item ) {

			// too deep - not interested
			if( $max_depth && ($item['depth'] > $max_depth) )
				continue;
			
			// back up a level so remove last too items
			if( $item['depth'] < $prev ) {
				$path = array_slice($path, 0, -2);
			}
			// same level so remove previous item
			elseif( $item['depth'] == $prev ) {
				array_pop($path);
			}

			$path[]       = $item['name'];
			$item['path'] = implode('.', $path);
			$tree[$id]    = $item;
			$prev         = $item['depth'];

		}

		if( $sort ) {
			uasort($tree, function( $a, $b ) {
				return strcmp($a['path'], $b['path']);
			});
		}

		return $tree;

	}

	public function visualise( $id, $max_depth = 0, $sort = false ) {

		$tree = array();
		$data = $this->getTree($id, $max_depth, $sort);

		if( $data ) {

			$offset = reset($data);
			$offset = (int) $offset['depth'];

			foreach( $data as $id => $item ) {
				$tree[$id] = str_repeat('|-- ', (int) $item['depth'] - $offset). $item['name'];
			}

		}

		return $tree;

	}

	public function rebuild( $sort = false ) {

		try {
			$this->db->begin();
			$this->db->execute("UPDATE {$this->table_name} SET lft = 0, rgt = 0");
			$this->rebuildNode($sort, 0);
			$this->db->commit();
		}
		catch( \Exception $e ) {
			$this->db->rollback();
			throw $e;
		}

		return $this;

	}

	protected function rebuildNode( $sort = false, $id = 0, $lft = 0 ) {

		$rgt = $lft + 1;

		$order_by = $sort ? "ORDER BY {$this->name_field} ASC" : '';

		// fetch a list of children of this node
		$nodes = $this->db->getCol("SELECT id FROM {$this->table_name} WHERE parent_id = ? {$order_by}", $id);

		foreach( $nodes as $child ) {
			$rgt = $this->rebuildNode($sort, $child, $rgt);
		}

		$this->db->execute("UPDATE {$this->table_name} SET lft = ?, rgt = ? WHERE id = ?", array($lft, $rgt, $id));

	    return $rgt + 1;

	}

	protected function findNodeById( $id ) {
		return $this->db->getRow("SELECT lft, rgt FROM {$this->table_name} WHERE id = ?", $id);
	}

}

// EOF