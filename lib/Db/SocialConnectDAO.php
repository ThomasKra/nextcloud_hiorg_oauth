<?php

namespace OCA\HiorgOAuth\Db;

use OCA\SocialLogin\Db\ConnectedLogin;
use OCP\IDBConnection;
use OCP\AppFramework\Db;
use OCP\AppFramework\Db\QBMapper;

class SocialConnectDAO extends QBMapper
{
  public function __construct(IDBConnection $db)
  {
    // Registrieren des QueryBuilderMapper - PREFIX wird automatisch vom QueryBuilder eingefügt
    parent::__construct($db, 'hiorgoauth_connect', ConnectedLogin::class);
  }

  /**
   * @param string $identifier hiorg oauth identifier
   * @return ConnectedLogin|null
   */
  public function find(string $identifier)
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
      ->from($this->getTableName())
      ->where(
        $qb->expr()->eq('identifier', $qb->createNamedParameter($identifier))
      );

    try {
      return $this->findEntity($qb);
    } catch (Db\DoesNotExistException $e) {
      return null;
    } catch (Db\MultipleObjectsReturnedException $e) {
      return null;
    }
  }

  /**
   * @param string $identifier hiorg oauth identifier
   * @return string|null Nextcloud user id that corresponds to the social login identifier
   */
  public function findUID($identifier)
  {
    $login = $this->find($identifier);
    return $login == null ? null : $login->uid;
  }

  /**
   * Login Verbinden
   * 
   * @param mixed $uid
   * @param mixed $identifier
   */
  public function connectLogin($uid, $identifier): void
  {
    $l = new ConnectedLogin();
    $l->setUid($uid);
    $l->setIdentifier($identifier);
    $this->insert($l);
  }

  /**
   * Login Verbindung löschen
   * 
   * @param mixed $identifier
   */
  public function disconnectLogin($identifier): void
  {
    $qb = $this->db->getQueryBuilder();
    $qb->delete($this->tableName)
      ->where(
        $qb->expr()->eq('identifier', $qb->createNamedParameter($identifier))
      );
    $qb->executeStatement();
  }

  /**
   * Alle Verbindungen löschen / abmelden
   * 
   * @param mixed $uid
   */
  public function disconnectAll($uid): void
  {
    $qb = $this->db->getQueryBuilder();

    $qb->delete($this->tableName)
      ->where(
        $qb->expr()->eq('uid', $qb->createNamedParameter($uid))
      );
    $qb->executeStatement();
  }

  // TODO: Wird das überhaupt benötigt? - Wird im SocialLogin nur aus der PersonalSettings aufgerufen
  /**
   * Verbundene Logins finden
   * 
   * @param string $uid
   * @return array
   */
  public function getConnectedLogins($uid): array
  {
    $qb = $this->db->getQueryBuilder();

    $qb->select('*')
      ->from($this->getTableName())
      ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid)));

    $entities = $this->findEntities($qb);
    $result = [];
    foreach ($entities as $e) {
      /**
       * @disregard P1014 Undefined property
       */
      $result[] = $e->identifier;
    }

    return $result;
  }
}
