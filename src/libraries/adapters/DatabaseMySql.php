<?php
/**
 * MySQL implementation
 *
 * This class defines the functionality defined by DatabaseInterface for a MySQL database.
 * It use EpiDatabase.
 * @author Hub Figuiere <hub@figuiere.net>
 */
class DatabaseMySql implements DatabaseInterface
{
  const currentSchemaVersion = 6;
  /**
    * Member variables holding the names to the SimpleDb domains needed and the database object itself.
    * @access private
    */
  private $errors = array(), $owner, $mySqlDb, $mySqlHost, $mySqlUser, $mySqlPassword, $mySqlTablePrefix;

  /**
    * Constructor
    *
    * @return void
    */
  public function __construct()
  {
    $mysql = getConfig()->get('mysql');
    EpiDatabase::employ('mysql', $mysql->mySqlDb,
                        $mysql->mySqlHost, $mysql->mySqlUser, Utility::decrypt($mysql->mySqlPassword));
    foreach($mysql as $key => $value) {
      $this->{$key} = $value;
    }

    $user = getConfig()->get('user');
    if($user !== null)
      $this->owner = $user->email;
  }

  /**
    * Delete an action from the database
    *
    * @param string $id ID of the action to delete
    * @return boolean
    */
  public function deleteAction($id)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}action` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    return ($res == 1);
  }

  /**
    * Delete credential
    *
    * @return boolean
    */
  public function deleteCredential($id)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}credential` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    return ($res == 1);
  }

  /**
    * Delete a group from the database
    *
    * @param string $id ID of the group to delete
    * @return boolean
    */
  public function deleteGroup($id)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}group` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    return ($res == 1);
  }

  /**
    * Delete a photo from the database
    *
    * @param string $id ID of the photo to delete
    * @return boolean
    */
  public function deletePhoto($id)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}photo` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    // TODO delete all versions
    return ($res == 1);
  }

  /**
    * Delete a tag from the database
    *
    * @param string $id ID of the tag to delete
    * @return boolean
    */
  public function deleteTag($id)
  {
    $resDel = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}tag` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    $resClean = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}elementTag` WHERE `owner`=:owner AND `tag`=:tag", array(':owner' => $this->owner, ':tag' => $id));
    return ($resDel == 1);
  }

  /**
    * Delete a webhook from the database
    *
    * @param string $id ID of the webhook to delete
    * @return boolean
    */
  public function deleteWebhook($id)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}webhook` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    return ($res == 1);
  }

  /**
    * Gets diagnostic information for debugging.
    *
    * @return array
    */
  public function diagnostics()
  {
    $diagnostics = array();
    $res = getDatabase()->execute("SELECT * FROM `{$this->mySqlTablePrefix}photo` WHERE owner=:owner LIMIT 1", array(':owner' => $this->owner));
    if($res == 1)
      $diagnostics[] = Utility::diagnosticLine(true, 'Database connectivity is okay.');
    else
      $diagnostics[] = Utility::diagnosticLine(false, 'Could not properly connect to the database.');

    return $diagnostics;
  }

  /**
    * Get a list of errors
    *
    * @return array
    */
  public function errors()
  {
    return $this->errors;
  }

  /**
    * Executes an upgrade script
    *
    * @return void
    */
  public function executeScript($file, $database)
  {
    if($database != 'mysql')
      return;

    include $file;
  }

  /**
    * Retrieve a credential with $id
    *
    * @param string $id ID of the credential to get
    * @return mixed Array on success, FALSE on failure
    */
  public function getCredential($id)
  {
    $cred = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}credential` WHERE `id`=:id AND owner=:owner",
                               array(':id' => $id, ':owner' => $this->owner));
    if(empty($cred))
    {
      return false;
    }
    return self::normalizeCredential($cred);
  }

  /**
    * Retrieve a credential by userToken
    *
    * @param string $userToken userToken of the credential to get
    * @return mixed Array on success, FALSE on failure
    */
  public function getCredentialByUserToken($userToken)
  {
    $cred = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}credential` WHERE userToken=:userToken AND owner=:owner",
                               array(':userToken' => $userToken, ':owner' => $this->owner));
    if(empty($cred))
    {
      return false;
    }
    return self::normalizeCredential($cred);
  }

  /**
    * Retrieve credentials
    *
    * @return mixed Array on success, FALSE on failure
    */
  public function getCredentials()
  {
    $res = getDatabase()->all("SELECT * FROM `{$this->mySqlTablePrefix}credential` WHERE owner=:owner AND status=1", array(':owner' => $this->owner));
    if($res === false)
    {
      return false;
    }
    $credentials = array();
    if(!empty($res))
    {
      foreach($res as $cred)
      {
        $credentials[] = self::normalizeCredential($cred);
      }
    }
    return $credentials;
  }

  /**
    * Retrieve group from the database specified by $id
    *
    * @param string $id id of the group to return
    * @return mixed Array on success, FALSE on failure
    */
  public function getGroup($id = null)
  {
    $res = getDatabase()->all("SELECT grp.*, memb.* FROM `{$this->mySqlTablePrefix}group` AS grp INNER JOIN `{$this->mySqlTablePrefix}groupMember` AS memb ON `grp`.`owner`=`memb`.`owner` WHERE `grp`.`id`=:id AND `grp`.`owner`=:owner", array(':id' => $id ,':owner' => $this->owner));
    if($res === false || empty($res))
      return false;

    $group = array('id' => $res[0]['id'], 'owner' => $res[0]['owner'], 'name' => $res[0]['name'], 'permission' => $res[0]['permission'], 'members' => array());
    foreach($res as $r)
      $group['members'][] = $r['email'];

    return self::normalizeGroup($group);
  }

  /**
    * Retrieve groups from the database optionally filter by member (email)
    *
    * @param string $email email address to filter by
    * @return mixed Array on success, NULL on empty, FALSE on failure
    */
  public function getGroups($email = null)
  {

    if(empty($email))
      $res = getDatabase()->all("SELECT `grp`.*, `memb`.`email` 
        FROM `{$this->mySqlTablePrefix}group` AS `grp` 
        INNER JOIN `{$this->mySqlTablePrefix}groupMember` AS `memb` ON `grp`.`owner`=`memb`.`owner` AND `grp`.`id`=`memb`.`group` 
        WHERE `grp`.`id` IS NOT NULL AND `grp`.`owner`=:owner 
        ORDER BY `grp`.`name`", array(':owner' => $this->owner));
    else
      $res = getDatabase()->all("SELECT `grp`.*, `memb`.`email` 
        FROM `{$this->mySqlTablePrefix}group` AS `grp` 
        INNER JOIN `{$this->mySqlTablePrefix}groupMember` AS `memb` ON `grp`.`owner`=`memb`.`owner` AND `grp`.`id`=`memb`.`group` 
        WHERE `memb`.`email`=:email AND `grp`.`id` IS NOT NULL AND `grp`.`owner`=:owner 
        ORDER BY `grp`.`name`", array(':email' => $email, ':owner' => $this->owner));

    if($res !== false)
    {
      $groups = array();
      if(!empty($res))
      {
        $tempGroups = array();
        foreach($res as $group)
        {
          if(!isset($tempGroups[$group['id']]))
            $tempGroups[$group['id']] = array('id' => $group['id'], 'name' => $group['name'], 'owner' => $group['owner'], 'permission' => $group['permission'], 'members' => array());
          $tempGroups[$group['id']]['members'][] = $group['email'];
        }
        foreach($tempGroups as $g)
          $groups[] = self::normalizeGroup($g);
      }
      return $groups;
    }

    return false;
  }

  /**
    * Get a photo specified by $id
    *
    * @param string $id ID of the photo to retrieve
    * @return mixed Array on success, FALSE on failure
    */
  public function getPhoto($id)
  {
    $photo = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}photo` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    if(empty($photo))
      return false;
    return self::normalizePhoto($photo);
  }

  /**
    * Retrieve the next and previous photo surrounding photo with $id
    *
    * @param string $id ID of the photo to get next and previous for
    * @return mixed Array on success, FALSE on failure
    */
  public function getPhotoNextPrevious($id, $filterOpts = null)
  {
    $buildQuery = $this->buildQuery($filterOpts, null, null);
    $photo = $this->getPhoto($id);
    if(!$photo)
      return false;

    // owner is in buildQuery
    $photo_prev = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}photo` {$buildQuery['where']} AND dateTaken> :dateTaken AND dateTaken IS NOT NULL ORDER BY dateTaken ASC LIMIT 1", array(':dateTaken' => $photo['dateTaken']));
    $photo_next = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}photo` {$buildQuery['where']} AND dateTaken< :dateTaken AND dateTaken IS NOT NULL ORDER BY dateTaken DESC LIMIT 1", array(':dateTaken' => $photo['dateTaken']));

    $ret = array();
    if($photo_prev)
      $ret['previous'] = self::normalizePhoto($photo_prev);
    if($photo_next)
      $ret['next'] = self::normalizePhoto($photo_next);

    return $ret;
  }

  /**
    * Retrieve a photo from the database and include the actions on the photo.
    * Actions are stored in a separate domain so the calls need to be made in parallel
    *
    * @param string $id ID of the photo to retrieve
    * @return mixed Array on success, FALSE on failure
    */
  public function getPhotoWithActions($id)
  {
    $photo = $this->getPhoto($id);
    $photo['actions'] = array();
    if($photo)
    {
      $actions = getDatabase()->all("SELECT * FROM `{$this->mySqlTablePrefix}action` WHERE owner=:owner AND targetType='photo' AND targetId=:id",
      	       array(':id' => $id, ':owner' => $this->owner));
      if(!empty($actions))
      {
        foreach($actions as $action)
           $photo['actions'][] = $action;
      }
    }
    return $photo;
  }

  /**
    * Get a list of a user's photos filtered by $filter, $limit and $offset
    *
    * @param array $filters Filters to be applied before obtaining the result
    * @return mixed Array on success, FALSE on failure
    */
  public function getPhotos($filters = array(), $limit, $offset = null)
  {
    $query = $this->buildQuery($filters, $limit, $offset);

    // buildQuery includes owner
    $photos = getDatabase()->all($sql = "SELECT * FROM `{$this->mySqlTablePrefix}photo` {$query['where']} {$query['sortBy']} {$query['limit']} {$query['offset']}");
    if(empty($photos))
      return false;
    for($i = 0; $i < count($photos); $i++)
    {
      $photos[$i] = self::normalizePhoto($photos[$i]);
    }

    // TODO evaluate SQL_CALC_FOUND_ROWS (indexes with the query builder might be hard to optimize)
    // http://www.mysqlperformanceblog.com/2007/08/28/to-sql_calc_found_rows-or-not-to-sql_calc_found_rows/
    $result = getDatabase()->one("SELECT COUNT(*) FROM `{$this->mySqlTablePrefix}photo` {$query['where']}");
    if(!empty($result))
    {
      $photos[0]['totalRows'] = $result['COUNT(*)'];
    }
    return $photos;
  }

  /**
    * Get a webhook specified by $id
    *
    * @param string $id ID of the webhook to retrieve
    * @return mixed Array on success, FALSE on failure
    */
  public function getWebhook($id)
  {
    $webhook = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}webhook` WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    if(empty($webhook))
      return false;
    return self::normalizeWebhook($webhook);
  }

  /**
    * Get all webhooks for a user
    *
    * @return mixed Array on success, FALSE on failure
    */
  public function getWebhooks($topic = null)
  {
    if($topic)
      $res = getDatabase()->all("SELECT * FROM `{$this->mySqlTablePrefix}webhook` WHERE owner=:owner AND `topic`='{$topic}'", array(':owner' => $this->owner));
    else
      $res = getDatabase()->all("SELECT * FROM `{$this->mySqlTablePrefix}webhook` WHERE owner=:owner", array(':owner' => $this->owner));

    if($res === false)
      return false;
    if(empty($res))
      return null;

    $webhooks = array();
    foreach($res as $webhook)
    {
      $webhooks[] = self::normalizeWebhook($webhook);
    }
    return $webhooks;
  }

  private function buildQuery($filters, $limit, $offset)
  {
    // TODO: support logic for multiple conditions
    $where = "WHERE owner='{$this->owner}'";
    $sortBy = 'ORDER BY dateTaken DESC';
    if(!empty($filters) && is_array($filters))
    {
      foreach($filters as $name => $value)
      {
        switch($name)
        {
          case 'groups':
            if(!is_array($value))
              $value = (array)explode(',', $value);
            foreach($value as $k => $v)
              $value[$k] = $this->_($v);
            $subquery = sprintf("(id IN (SELECT element FROM `{$this->mySqlTablePrefix}elementGroup` WHERE `owner`='%s' AND `type`='%s' AND `group` IN('%s')) OR permission='1')",
              $this->_($this->owner), 'photo', implode("','", $value));
            $where = $this->buildWhere($where, $subquery);
            break;
          case 'page':
            if($value > 1)
            {
              $value = min($value, 40); // 40 pages at max of 2,500 recursion limit means 100k photos
              $offset = ($limit * $value) - $limit;
            }
            break;
          case 'permission':
            $where = $this->buildWhere($where, "permission='1'");
            break;
          case 'sortBy':
            $sortBy = 'ORDER BY ' . str_replace(',', ' ', $value);
            $field = substr($value, 0, strpos($value, ','));
            $where = $this->buildWhere($where, "{$field} is not null");
            break;
          case 'tags':
            // TODO there are a few issues here
            // 1) It's inefficient (uses file sort)
            // 2) It's using OR logic instead of AND
            if(!is_array($value))
              $value = (array)explode(',', $value);
            foreach($value as $k => $v)
              $value[$k] = $this->_($v);
            $subquery = sprintf("id IN (SELECT element FROM `{$this->mySqlTablePrefix}elementTag` WHERE `owner`='%s' AND `type`='%s' AND `tag` IN ('%s'))",
              $this->_($this->owner), 'photo', implode("','", $value));
            $where = $this->buildWhere($where, $subquery);
            break;
        }
      }
    }

    $limit_sql = '';
    if($limit)
      $limit_sql = "LIMIT {$limit}";

    $offset_sql = '';
    if($offset)
      $offset_sql = "OFFSET {$offset}";

    $ret = array('where' => $where, 'sortBy' => $sortBy, 'limit' => $limit_sql, 'offset' => $offset_sql);
    return $ret;
  }

  /**
    * Get a tag
    * Consistent read set to false
    *
    * @param string $tag tag to be retrieved
    * @return mixed Array on success, FALSE on failure
    */
  public function getTag($tag)
  {
    $tag = getDatabase()->one('SELECT * FROM `{$this->mySqlTablePrefix}tag` WHERE `id`=:id AND owner=:owner', array(':id' => $tag));
    // TODO this should be in the normalize method
    if($tag['params'])
      $tag = array_merge($tag, json_decode($tag['params'], 1));
    unset($tag['params']);
    return $tag;
  }

  /**
    * Get tags filtered by $filter
    * Consistent read set to false
    *
    * @param array $filters Filters to be applied to the list
    * @return mixed Array on success, FALSE on failure
    */
  public function getTags($filter = array())
  {
    $countField = 'countPublic';
    if(isset($filter['permission']) && $filter['permission'] == 0)
      $countField = 'countPrivate';
    $tags = getDatabase()->all("SELECT * FROM `{$this->mySqlTablePrefix}tag` WHERE `id` IS NOT NULL AND `owner`=:owner AND `{$countField}` IS NOT NULL AND `{$countField}` > '0' ORDER BY `id`", array(':owner' => $this->owner));
    foreach($tags as $key => $tag)
    {
      // TODO this should be in the normalize method
      if($tag['extra'])
        $tags[$key] = array_merge($tag, json_decode($tag['extra'], 1));
      unset($tags[$key]['params']);
    }
    if(empty($tags))
      return null;

    return $tags;
  }

  /**
    * Get the user record entry.
    *
    * @return mixed Array on success, NULL if user record is empty, FALSE on error
    */
  public function getUser($owner = null)
  {
    if($owner === null)
      $owner = $this->owner;

    $res = getDatabase()->one("SELECT * FROM `{$this->mySqlTablePrefix}user` WHERE `id`=:owner", array(':owner' => $owner));
    if($res)
    {
      return self::normalizeUser($res);
    }
    return null;
  }

  /**
    * Identification method to return array of strings.
    *
    * @return array
    */
  public function identity()
  {
    return array('mysql');
  }

  /**
    * Initialize the database by creating the database and tables needed.
    * This is called from the Setup controller.
    *
    * @return boolean
    */
  public function initialize()
  {
    // email address has to be unique
    // getting a null back from getUser() means we can proceed
    if($this->version() !== '0.0.0' && getSession()->get('isEditMode') === false)
    {
      $userConfig = getConfig()->get('user');
      $user = true;
      if(isset($userConfig->email))
        $user = $this->getUser($userConfig->email);

      if($user !== null)
      {
        getLogger()->crit(sprintf('Could not initialize user for MySql due to email conflict (%s).', $userConfig->email));
        return false;
      }
      return true;
    }

    try
    {
      $this->executeScript(sprintf('%s/upgrade/db/mysql/mysql-base.php', getConfig()->get('paths')->configs), 'mysql');
      return true;
    }
    catch(EpiDatabaseException $e)
    {
      return false;
    }
  }

  /**
    * Update the information for an existing credential.
    * This method overwrites existing values present in $params.
    *
    * @param string $id ID of the credential to update.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function postCredential($id, $params)
  {
    $params = self::prepareCredential($params);
    $bindings = array();
    if(isset($params['::bindings']))
    {
      $bindings = $params['::bindings'];
    }
    $stmt = self::sqlUpdateExplode($params, $bindings);
    $bindings[':id'] = $id;
    $bindings[':owner'] = $this->owner;

    $result = getDatabase()->execute("UPDATE `{$this->mySqlTablePrefix}credential` SET {$stmt} WHERE `id`=:id AND owner=:owner", $bindings);

    return ($result == 1);
  }

  /**
    * Update the information for an existing credential.
    * This method overwrites existing values present in $params.
    *
    * @param string $id ID of the credential to update.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function postGroup($id, $params)
  {
    $members = false;
    if(isset($params['members']))
    {
      $members = !empty($params['members']) ? (array)explode(',', $params['members']) : null;
      unset($params['members']);
    }
    $params = self::prepareGroup($id, $params);
    $bindings = array();
    if(isset($params['::bindings']))
    {
      $bindings = $params['::bindings'];
    }
    $stmt = self::sqlUpdateExplode($params, $bindings);
    $bindings[':id'] = $id;
    $bindings[':owner'] = $this->owner;

    $result = getDatabase()->execute("UPDATE `{$this->mySqlTablePrefix}group` SET {$stmt} WHERE `id`=:id AND owner=:owner", $bindings);
    if($members !== false)
    {
      $this->deleteGroupMembers($id);
      $this->addGroupMembers($id, $members);
    }

    return $result !== false;
  }

  /**
    * Update the information for an existing photo.
    * This method overwrites existing values present in $params.
    *
    * @param string $id ID of the photo to update.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function postPhoto($id, $params)
  {
    $tags = false;
    if(isset($params['tags']))
      $tags = !empty($params['tags']) ? (array)explode(',', $params['tags']) : null;

    // TODO, @hfiguiere, what's this do exactly? Seems unnecessary. -- @jmathai
    foreach($params as $key => $val)
    {
      if(preg_match('/^path\d+x\d+/', $key))
      {
        $versions[$key] = $val;
        unset($params[$key]);
      }
    }

    if(!empty($params))
    {
      if(isset($params['groups']))
      {
        $this->deleteGroupsFromElement($id, 'photo');
        $this->addGroupsToElement($id, $params['groups'], 'photo');
        // TODO: Generalize this and use for tags too -- @jmathai
        $params['groups'] = preg_replace(array('/^,|,$/','/,{2,}/'), array('', ','), implode(',', $params['groups']));
      }
      $params = self::preparePhoto($id, $params);
      unset($params['id']);
      $bindings = $params['::bindings'];
      $stmt = self::sqlUpdateExplode($params, $bindings);
      $res = getDatabase()->execute("UPDATE `{$this->mySqlTablePrefix}photo` SET {$stmt} WHERE `id`=:id AND owner=:owner", 
        array_merge($bindings, array(':id' => $id, ':owner' => $this->owner)));
    }
    if(!empty($versions))
      $resVersions = $this->postVersions($id, $versions);

    if($tags !== false)
    {
      $this->deleteTagsFromElement($id, 'photo');
      if(!empty($tags))
      {
        // TODO combine this into a multi row insert in addTagsToElement
        foreach($tags as $tag)
          $this->addTagToElement($id, $tag, 'photo');
      }
    }
    return (isset($res) && $res == 1) || (isset($resVersions) && $resVersions);
  }

  /**
    * Update a single tag.
    * The $params should include the tag in the `id` field.
    * [{id: tag1, count:10, longitude:12.34, latitude:56.78},...]
    *
    * @param array $params Tags and related attributes to update.
    * @return boolean
    */
  public function postTag($id, $params)
  {
    if(!isset($params['id']))
    {
      $params['id'] = $id;
    }
    $params['owner'] = $this->owner;
    $params = self::prepareTag($params);
    if(isset($params['::bindings']))
      $bindings = $params['::bindings'];
    else
      $bindings = array();

    $stmtIns = self::sqlInsertExplode($params, $bindings);
    $stmtUpd = self::sqlUpdateExplode($params, $bindings);

    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}tag` ({$stmtIns['cols']}) VALUES ({$stmtIns['vals']}) ON DUPLICATE KEY UPDATE {$stmtUpd}", $bindings);
    return ($result !== false);
  }

  /**
    * Update multiple tags.
    * The $params should include the tag in the `id` field.
    * [{id: tag1, count:10, longitude:12.34, latitude:56.78},...]
    *
    * @param array $params Tags and related attributes to update.
    * @return boolean
    */
  public function postTags($params)
  {
    if(empty($params))
      return true;
    foreach($params as $tagObj)
    {
      $res = $this->postTag($tagObj['id'], $tagObj);
    }
    return $res;
  }

  /**
    * Update the information for the user record.
    * This method overwrites existing values present in $params.
    *
    * @param string $id ID of the user to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function postUser($params)
  {
    $params = self::prepareUser($params);
    $res = getDatabase()->execute("UPDATE `{$this->mySqlTablePrefix}user` SET `extra`=:extra WHERE `id`=:id", array(':id' => $this->owner, ':extra' => $params));
    return ($res == 1);
  }

  /**
    * Update the information for the webhook record.
    *
    * @param string $id ID of the webhook to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function postWebhook($id, $params)
  {
    $stmt = self::sqlUpdateExplode($params);
    $res = getDatabase()->execute("UPDATE `{$this->mySqlTablePrefix}webhook` SET {$stmt} WHERE `id`=:id AND owner=:owner", array(':id' => $id, ':owner' => $this->owner));
    return ($res == 1);
  }

  /**
    * Add a new action to the database
    * This method does not overwrite existing values present in $params - hence "new action".
    *
    * @param string $id ID of the action to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function putAction($id, $params)
  {
    $stmt = self::sqlInsertExplode($params);
    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}action` (id,{$stmt['cols']}) VALUES (:id,{$stmt['vals']})", array(':id' => $id));
    return ($result !== false);
  }

  /**
    * Add a new credential to the database
    * This method does not overwrite existing values present in $params - hence "new credential".
    *
    * @param string $id ID of the credential to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function putCredential($id, $params)
  {
    $params['owner'] = $this->owner;
    if(!isset($params['id']))
      $params['id'] = $id;
    $params = self::prepareCredential($params);
    $stmt = self::sqlInsertExplode($params);
    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}credential` ({$stmt['cols']}) VALUES ({$stmt['vals']})");

    return ($result !== false);
  }

  /**
    * Alias of postGroup
    */
  public function putGroup($id, $params)
  {
    $params['owner'] = $this->owner;
    if(!isset($params['id']))
      $params['id'] = $id;
    $members = false;
    if(isset($params['members']))
    {
      $members = !empty($params['members']) ? (array)explode(',', $params['members']) : null;
      unset($params['members']);
    }
    $params = self::prepareGroup($id, $params);
    $stmt = self::sqlInsertExplode($params);
    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}group` ({$stmt['cols']}) VALUES ({$stmt['vals']})");
    if($members !== false)
      $this->addGroupMembers($id, $members);
    return $result !== false;
  }

  /**
    * Add a new photo to the database
    * This method does not overwrite existing values present in $params - hence "new photo".
    *
    * @param string $id ID of the photo to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function putPhoto($id, $params)
  {
    $params['id'] = $id;
    $params['owner'] = $this->owner;
    $tags = null;
    if(isset($params['tags']) && !empty($params['tags']))
      $tags = (array)explode(',', $params['tags']);
    $params = self::preparePhoto($id, $params);
    $bindings = $params['::bindings'];
    $stmt = self::sqlInsertExplode($params, $bindings);
    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}photo` ({$stmt['cols']}) VALUES ({$stmt['vals']})", $bindings);
    if(!empty($tags))
    {
      foreach($tags as $tag)
        $this->addTagToElement($id, $tag, 'photo');
    }
    return ($result !== false);
  }

  /**
    * Add a new tag to the database
    * This method does not overwrite existing values present in $params - hence "new tag".
    *
    * @param string $id ID of the user to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function putTag($id, $params)
  {
    return $this->postTag($id, $params);
  }

  /**
    * Add a new user to the database
    * This method does not overwrite existing values present in $params - hence "new user".
    *
    * @param string $id ID of the user to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function putUser($params)
  {
    $params = self::prepareUser($params);
    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}user` (`id`,`extra`) VALUES (:id,:extra)", array(':id' => $this->owner, ':extra' => $params['extra']));
    return $result !== false;
  }

  /**
    * Add a new webhook to the database
    *
    * @param string $id ID of the webhook to update which is always 1.
    * @param array $params Attributes to update.
    * @return boolean
    */
  public function putWebhook($id, $params)
  {
    $stmt = self::sqlInsertExplode($params);
    $result = getDatabase()->execute("INSERT INTO `{$this->mySqlTablePrefix}webhook` (id,owner,{$stmt['cols']}) VALUES (:id,:owner,{$stmt['vals']})", array(':id' => $id, ':owner' => $this->owner));
    return $result !== false;
  }

  /**
    * Get the current database version
    *
    * @return string Version number
    */
  public function version()
  {
    try
    {
      $result = getDatabase()->one("SELECT * from `{$this->mySqlTablePrefix}admin` WHERE `key`=:key", array(':key' => 'version'));
      if($result)
        return $result['value'];
    }
    catch(EpiDatabaseException $e)
    {
      return '0.0.0';
    }

    return '0.0.0';
  }

  /**
    * Add members to a group
    *
    * @param string $id Group id
    * @param array $members Members to be added
    * @return boolean
    */
  private function addGroupMembers($id, $members)
  {
    if(empty($id) || empty($members))
      return false;

    $sql = "REPLACE INTO `{$this->mySqlTablePrefix}groupMember`(`owner`, `group`, `email`) VALUES ";
    foreach($members as $member)
      $sql .= sprintf("('%s', '%s', '%s'),", $this->_($this->owner), $this->_($id), $this->_($member));

    $sql = substr($sql, 0, -1);
    $res = getDatabase()->execute($sql);
    return $res > 0;
  }

  /**
    * Insert groups into the mapping table
    *
    * @param string $id Element id (id of the photo or video)
    * @param string $tag Tag to be added
    * @param string $type Element type (photo or video)
    * @return boolean
    */
  private function addGroupsToElement($id, $groups, $type)
  {
    if(empty($id) || empty($groups) || empty($type))
      return false;

    $hasGroup = false;
    $sql = "REPLACE INTO `{$this->mySqlTablePrefix}elementGroup`(`owner`, `type`, `element`, `group`) VALUES";
    foreach($groups as $group)
    {
      if(strlen($group) > 0)
      {
        $sql .= sprintf("('%s', '%s', '%s', '%s'),", $this->_($this->owner), $this->_($type), $this->_($id), $this->_($group));
        $hasGroup = true;
      }
    }

    if(!$hasGroup)
      return false;

    $sql = substr($sql, 0, -1);
    $res = getDatabase()->execute($sql);
    return $res > 0;
  }

  /**
    * Insert tags into the mapping table
    *
    * @param string $id Element id (id of the photo or video)
    * @param string $tag Tag to be added
    * @param string $type Element type (photo or video)
    * @return boolean
    */
  private function addTagToElement($id, $tag, $type)
  {
    $res = getDatabase()->execute("REPLACE INTO `{$this->mySqlTablePrefix}elementTag`(`owner`, `type`, `element`, `tag`) VALUES(:owner, :type, :element, :tag)", array(':owner' => $this->owner, ':type' => $type, ':element' => $id, ':tag' => $tag));
    return $res > 0;
  }

  /**
    * Utility function to help build the WHERE clause for SELECT statements.
    * (Taken from DatabaseSimpleDb)
    * TODO possibly put duplicate code in a utility class
    *
    * @param string $existing Existing where clause.
    * @param string $add Clause to add.
    * @return string
    */
  private function buildWhere($existing, $add)
  {
    if(empty($existing))
      return "where {$add} ";
    else
      return "{$existing} and {$add} ";
  }

  /**
    * Delete groups for an element from the mapping table
    *
    * @param string $id Element id (id of the photo or video)
    * @param string $tag Tag to be added
    * @param string $type Element type (photo or video)
    * @return boolean
    */
  private function deleteGroupsFromElement($id, $type)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}elementGroup` WHERE `owner`=:owner AND `type`=:type AND `element`=:element", array(':owner' => $this->owner, ':type' => $type, ':element' => $id));
    return $res !== false;
  }

  /**
    * Delete members from a group
    *
    * @param string $id Element id (id of the photo or video)
    * @return boolean
    */
  private function deleteGroupMembers($id)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}groupMember` WHERE `owner`=:owner AND `group`=:group", array(':owner' => $this->owner, ':group' => $id));
    return $res !== false;
  }

  /**
    * Delete tags for an element from the mapping table
    *
    * @param string $id Element id (id of the photo or video)
    * @param string $tag Tag to be added
    * @param string $type Element type (photo or video)
    * @return boolean
    */
  private function deleteTagsFromElement($id, $type)
  {
    $res = getDatabase()->execute("DELETE FROM `{$this->mySqlTablePrefix}elementTag` WHERE `owner`=:owner AND `type`=:type AND `element`=:element", array(':owner' => $this->owner, ':type' => $type, ':element' => $id));
    return $res !== false;
  }

  /**
    * Get all the versions of a given photo
    * TODO this can be eliminated once versions are in the json field
    *
    * @param string $id Id of the photo of which to get versions of
    * @return array Array of versions
    */
  private function getPhotoVersions($id)
  {
    $versions = getDatabase()->all("SELECT `key`,path FROM `{$this->mySqlTablePrefix}photoVersion` WHERE `id`=:id AND owner=:owner",
                 array(':id' => $id, ':owner' => $this->owner));
    if(empty($versions))
      return false;
    return $versions;
  }

  /**
   * Explode params associative array into SQL insert statement lists
   * Return an array with 'cols' and 'vals'
   */
  private function sqlInsertExplode($params, $bindings = array())
  {
    $stmt = array('cols' => '', 'vals' => '');
    foreach($params as $key => $value)
    {
      if($key == '::bindings')
        continue;
      if(!empty($stmt['cols']))
        $stmt['cols'] .= ",";
      if(!empty($stmt['vals']))
        $stmt['vals'] .= ",";
      $stmt['cols'] .= $key;
      if(!empty($bindings) && array_key_exists($value, $bindings))
        $stmt['vals'] .= "{$value}";
      else
        $stmt['vals'] .= sprintf("'%s'", $this->_($value));
    }
    return $stmt;
  }

  /**
    * Normalizes data from MySql into schema definition
    *
    * @param SimpleXMLObject $raw An action from SimpleDb in SimpleXML.
    * @return array
    */
  private function normalizeAction($raw)
  {
    // TODO shouldn't we require and use this method?
  }

  /**
    * Normalizes data from simpleDb into schema definition
    * TODO this should eventually translate the json field
    *
    * @param SimpleXMLObject $raw A photo from SimpleDb in SimpleXML.
    * @return array
    */
  private function normalizePhoto($photo)
  {
    $photo['appId'] = getConfig()->get('application')->appId;

    $versions = $this->getPhotoVersions($photo['id']);
    if($versions && !empty($versions))
    {
      foreach($versions as $version)
      {
        $photo[$version['key']] = $version['path'];
      }
    }

    $photo['tags'] = strlen($photo['tags']) ? (array)explode(",", $photo['tags']) : array();
    $photo['groups'] = strlen($photo['groups']) ? (array)explode(",", $photo['groups']) : array();

    $exif = (array)json_decode($photo['exif']);
    $extra = (array)json_decode($photo['extra']);
    $photo = array_merge($photo, $exif, $extra);
    unset($photo['exif'], $photo['extra']);

    return $photo;
  }

  /**
    * Normalizes data from simpleDb into schema definition
    * TODO this should eventually translate the json field
    *
    * @param SimpleXMLObject $raw A tag from SimpleDb in SimpleXML.
    * @return array
    */
  private function normalizeTag($raw)
  {
    return $raw;
  }

  private function normalizeWebhook($raw)
  {
    return $raw;
  }

  /**
    * Normalizes data from simpleDb into schema definition
    * TODO this should eventually translate the json field
    *
    * @param SimpleXMLObject $raw A tag from SimpleDb in SimpleXML.
    * @return array
    */
  private function normalizeUser($raw)
  {
    $jsonParsed = json_decode($raw['extra'], 1);
    if(!empty($jsonParsed))
    {
      foreach($jsonParsed as $key => $value)
        $raw[$key] = $value;
    }
    unset($raw['extra']);
    return $raw;
  }

  private function normalizeCredential($raw)
  {
    if(isset($raw['permissions']) && !empty($raw['permissions']))
      $raw['permissions'] = (array)explode(',', $raw['permissions']);

    return $raw;
  }


  /**
    * Normalizes data from simpleDb into schema definition
    *
    * @param SimpleXMLObject $raw An action from SimpleDb in SimpleXML.
    * @return array
    */
  private function normalizeGroup($raw)
  {
    if(!isset($raw['members']) || empty($raw['members']))
      $raw['members'] = array();
    return $raw;
  }

  /** Prepare credential to store in the database
   */
  private function prepareCredential($params)
  {
    if(isset($params['permissions']))
      $params['permissions'] = implode(',', $params['permissions']);

    return $params;
  }

  /**
    * Formats a photo to be updated or added to the database.
    * Primarily to properly format tags as an array.
    *
    * @param string $id ID of the photo.
    * @param array $params Parameters for the photo to be normalized.
    * @return array
    */
  private function preparePhoto($id, $params)
  {
    $paramsOut = $bindings = $exif = $extra = array();
    foreach($params as $key => $value)
    {
      switch($key)
      {
        case 'exif':
        case 'exifOrientation':
        case 'exifCameraMake':
        case 'exifCameraModel':
        case 'exifExposureTime':
        case 'exifFNumber':
        case 'exifMaxApertureValue':
        case 'exifMeteringMode':
        case 'exifFlash':
        case 'exifFocalLength':
        case 'exifISOSpeed':
        case 'gpsAltitude':
        case 'latitude':
        case 'longitude':
          $exif[$key] = $value;
          break;
        case 'extra':
          break;
        default:
          $bindings[":{$key}"] = $value;
          $paramsOut[$key] = ":{$key}";
          break;
      }
    }
    if(!empty($exif))
    {
      $bindings[":exif"] = json_encode($exif);
      $paramsOut['exif'] = ':exif';
    }
    if(!empty($extra))
    {
      $bindings[":extra"] = json_encode($extra);
      $paramsOut['extra'] = ':extra';
    }
    $paramsOut['::bindings'] = $bindings;
    return $paramsOut;
  }

  /** Prepare tags to store in the database
   */
  private function prepareTag($params)
  {
    $bindings = array();
    if(!empty($params['id']))
    {
      $bindings[':id'] = $params['id'];
      $params['id'] = ':id';
    }
    if(!empty($bindings))
    {
      $params['::bindings'] = $bindings;
    }
    return $params;
  }

  /** Prepare user to store in the database
   */
  private function prepareUser($params)
  {
    $ret = array();
    if(isset($params) && is_array($params) && !empty($params))
    {
      foreach($params as $key => $val)
        $ret[$key] = $val;
    }
    return json_encode($ret);
  }

  /**
    * Formats a group to be updated or added to the database.
    * Primarily to properly format members as an array.
    *
    * @param string $id ID of the group.
    * @param array $params Parameters for the group to be normalized.
    * @return array
    */
  private function prepareGroup($id, $params)
  {
    return $params;
  }

  /**
    * Inserts a new version of photo with $id and $versions
    * TODO this should be in a json field in the photo table
    *
    * @param string $id ID of the photo.
    * @param array $versions Versions to the photo be inserted
    * @return array
    */
  private function postVersions($id, $versions)
  {
    foreach($versions as $key => $value)
    {
      // TODO this is gonna fail if we already have the version -- hfiguiere
      // Possibly use REPLACE INTO? -- jmathai
      $result = getDatabase()->execute("INSERT INTO {$this->mySqlTablePrefix}photoVersion (`id`, `owner`, `key`, `path`) VALUES(:id, :owner, :key, :value)",
        array(':id' => $id, ':owner' => $this->owner, ':key' => $key, ':value' => $value));
    }
    // TODO, what type of return value should we have here -- jmathai
    return ($result != 1);
  }

  /**
   * Explode params associative array into SQL update statement lists
   * TODO, have this work with PDO named parameters
   *
   * Return a string
   */
  private function sqlUpdateExplode($params, $bindings = array())
  {
    $stmt = '';
    foreach($params as $key => $value)
    {
      if($key == '::bindings')
        continue;
      if(!empty($stmt)) {
        $stmt .= ",";
      }
      if(!empty($bindings) && array_key_exists($value, $bindings))
        $stmt .= "`{$key}`={$value}";
      else
        $stmt .= sprintf("`%s`='%s'", $key, $this->_($value));
    }
    return $stmt;
  }

  /**
   * Wrapper function for escaping strings for queries
   *
   * @param string $str String to be escaped
   * @return string
   */
  private function _($str)
  {
    return addslashes($str);
  }
}
