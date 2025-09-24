<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

require_once __DIR__ . '/../core/modules/moddiscountrules.class.php';
require_once __DIR__ . '/discountrule.class.php';
require_once __DIR__ . '/discountSearch.class.php';

/**
 * \file    class/api_discountrule.class.php
 * \ingroup discountrules
 * \brief   File for API management of discountrule.
 */

/**
 * API class for discountrules discountrule
 *
 * @smart-auto-routing false
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class discountrules extends DolibarrApi
{
    /**
     * @var array   $FIELDS     Mandatory fields, checked when create and update object
     */
    static $FIELDS = array(
        'name'
    );

	protected $db = null;

    /**
     * @var DiscountRule $discountrule {@type DiscountRule}
     */
    public $discountrule;

    /**
     * Constructor
     *
     */
    function __construct()
    {
		global $db, $conf;
		$this->db = $db;
        //$this->mod = new moddiscountrules($this->db);
        $this->discountrule = new DiscountRule($this->db);
    }

    /**
     * Get properties of a discountrule object
     *
     * Return an array with discountrule informations
     *
     * @param 	int 	$id ID of discountrule
     * @return 	array|mixed data without useless information
	 *
     * @url	GET /{id}
     * @throws 	RestException
     */
    function get($id)
    {
		if(! DolibarrApiAccess::$user->hasRight('discountrules','read')) {
			throw new RestException(401);
		}

        $result = $this->discountrule->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'discountrule not found');
        }

		if( ! DolibarrApi::_checkAccessToResource('discountrule',$this->discountrule->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

		return $this->_cleanObjectDatas($this->discountrule);
    }

    /**
     * List discountrules
     *
     * Get a list of discountrules
     *
     * @param int		$mode		Use this param to filter list
     * @param string	$sortfield	Sort field
     * @param string	$sortorder	Sort order
     * @param int		$limit		Limit for list
     * @param int		$page		Page number
     * @param string    $sqlfilters Other criteria to filter answers separated by a comma. Syntax example "(t.ref:like:'SO-%') and (t.date_creation:<:'20160101') or (t.import_key:=:'20160101')"
     * @return array Array of discountrule objects
     *
     * @url	GET /
     */
    function index($sortfield = "t.rowid", $sortorder = 'ASC', $limit = 0, $page = 0, $sqlfilters = '') {

        $obj_ret = array();

        $socid = DolibarrApiAccess::$user->societe_id ? DolibarrApiAccess::$user->societe_id : '';

        // If the internal user must only see his customers, force searching by him
        if (! DolibarrApiAccess::$user->hasRight('societe','client','voir') && !$socid) $search_sale = DolibarrApiAccess::$user->id;

        $sql = "SELECT t.rowid";
        if ((!DolibarrApiAccess::$user->hasRight('societe','client','voir') && !$socid) || $search_sale > 0) $sql .= ", sc.fk_soc, sc.fk_user"; // We need these fields in order to filter by sale (including the case where the user can only see his prospects)
        $sql.= " FROM ".$this->db->prefix()."discountrule as t";

        if ((!DolibarrApiAccess::$user->hasRight('societe','client','voir') && !$socid) || $search_sale > 0) $sql.= ", ".$this->db->prefix()."societe_commerciaux as sc"; // We need this table joined to the select in order to filter by sale
        // $sql.= ", ".$this->db->prefix()."c_stcomm as st";
		$sql .= " WHERE ";
        // $sql.= " WHERE s.fk_stcomm = st.id";

        $sql.= ' t.entity IN ('.getEntity('discountrule').')';
        if ((!DolibarrApiAccess::$user->hasRight('societe','client','voir') && !$socid) || $search_sale > 0) $sql.= " AND t.fk_soc = sc.fk_soc";
        if ($socid) $sql.= " AND t.fk_soc = ".$socid;
        if ($search_sale > 0) $sql.= " AND t.rowid = sc.fk_soc";		// Join for the needed table to filter by sale
        // Insert sale filter
        if ($search_sale > 0)
        {
            $sql .= " AND sc.fk_user = ".$search_sale;
        }
        if ($sqlfilters)
        {
            if (! DolibarrApi::_checkFilters($sqlfilters))
            {
                throw new RestException(503, 'Error when validating parameter sqlfilters '.$sqlfilters);
            }
	        $regexstring='\(([^:\'\(\)]+:[^:\'\(\)]+:[^:\(\)]+)\)';
            $sql.=" AND (".preg_replace_callback('/'.$regexstring.'/', 'DolibarrApi::_forge_criteria_callback', $sqlfilters).")";
        }

        $sql.= $this->db->order($sortfield, $sortorder);
        if ($limit)	{
            if ($page < 0)
            {
                $page = 0;
            }
            $offset = $limit * $page;

            $sql.= $this->db->plimit($limit + 1, $offset);
        }

        $result = $this->db->query($sql);
        if ($result)
        {
            $num = $this->db->num_rows($result);
            while ($i < $num)
            {
                $obj = $this->db->fetch_object($result);
                $discountrule_static = new DiscountRule($this->db);
                if($discountrule_static->fetch($obj->rowid)) {
                    $obj_ret[] = parent::_cleanObjectDatas($discountrule_static);
                }
                $i++;
            }
        }
        else {
            throw new RestException(503, 'Error when retrieve discountrule list: ' . $sql);
        }
        if( ! count($obj_ret)) {
            throw new RestException(404, 'No discountrule found');
        }
		return $obj_ret;
    }

    /**
     * Create discountrule object
     *
     * @param array $request_data   Request datas
     * @return int  ID of discountrule
     *
     * @url	POST /
     */
    function post($request_data = NULL)
    {
        if(! DolibarrApiAccess::$user->hasRight('discountrules','create')) {
			throw new RestException(401);
		}
        // Check mandatory fields
        $result = $this->_validate($request_data);

        foreach($request_data as $field => $value) {
            $this->discountrule->$field = $value;
        }
        if( ! $this->discountrule->create(DolibarrApiAccess::$user)) {
            throw new RestException(500);
        }
        return $this->discountrule->id;
    }

    /**
     * Update discountrule
     *
     * @param int   $id             Id of discountrule to update
     * @param array $request_data   Datas
     * @return int
     *
     * @url	PUT /{id}
     */
    function put($id, $request_data = NULL)
    {
        if(! DolibarrApiAccess::$user->hasRight('discountrules','create')) {
			throw new RestException(401);
		}

        $result = $this->discountrule->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'discountrule not found');
        }

		if( ! DolibarrApi::_checkAccessToResource('discountrule',$this->discountrule->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

        foreach($request_data as $field => $value) {
            $this->discountrule->$field = $value;
        }

        if($this->discountrule->update($id, DolibarrApiAccess::$user))
            return $this->get ($id);

        return false;
    }

    /**
     * Delete discountrule
     *
     * @param   int     $id   discountrule ID
     * @return  array
     *
     * @url	DELETE /{id}
     */
    function delete($id)
    {
        if(! DolibarrApiAccess::$user->hasRight('discountrules','delete')) {
			throw new RestException(401);
		}
        $result = $this->discountrule->fetch($id);
        if( ! $result ) {
            throw new RestException(404, 'discountrule not found');
        }

		if( ! DolibarrApi::_checkAccessToResource('discountrule',$this->discountrule->id)) {
			throw new RestException(401, 'Access not allowed for login '.DolibarrApiAccess::$user->login);
		}

        if( !$this->discountrule->delete($id))
        {
            throw new RestException(500);
        }

         return array(
            'success' => array(
                'code' => 200,
                'message' => 'discountrule deleted'
            )
        );

    }

    /**
     * Validate fields before create or update object
     *
     * @param array $data   Data to validate
     * @return array
     *
     * @throws RestException
     */
    function _validate($data)
    {
        $discountrule = array();
        foreach (discountruleApi::$FIELDS as $field) {
            if (!isset($data[$field]))
                throw new RestException(400, "$field field missing");
            $discountrule[$field] = $data[$field];
        }
        return $discountrule;
    }

    /**
     * search discount price with discount rules
     * @param int $fk_product product Id
     * @param int $qty quantity
     * @param int $fk_c_typent
     * @param string $timestmap understood by date()
     * @param int $fk_company
     * @param int $fk_country
     * @param int $fk_project
     * @return array new price
     *
     * @url     POST /search
     */
    function search(int $fk_product, int $qty, int $fk_c_typent = 0, string $timestamp = '', int $fk_company = 0, int $fk_country = 0, int $fk_project = 0)
    {
        if (!DolibarrApiAccess::$user->hasRight('discountrules', 'read')) {
            throw new RestException(401);
        }
        // Check mandatory fields
		/*
        $fk_product = GETPOST('fk_product', 'int');
        $fk_project = GETPOST('fk_project', 'int');
        $fk_company = GETPOST('fk_company', 'int');
        $fk_country = GETPOST('fk_country', 'int');
        $qty = GETPOST('qty', 'int');
        $fk_c_typent = GETPOST('fk_c_typent', 'int');
        $date = GETPOST('date', 'none');
		*/

        $search = new DiscountSearch($this->db);
        return $search->search($qty, $fk_product, $fk_company, $fk_project, array(), array(), $fk_c_typent, $fk_country, 0, $timestamp);
    }
}
