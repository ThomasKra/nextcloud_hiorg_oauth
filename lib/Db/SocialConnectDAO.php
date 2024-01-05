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
   * Suchen einer Verbindung in der Datenbank
   * 
   * @param string $identifier hiorg oauth identifier
   * @return string|null User uid
   */
  public function findUID($identifier): string | null
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
    if (method_exists($qb, 'executeStatement')) {
      $qb->executeStatement();
    } else {
      /**
       * @disregard P1007 Deprectated
       */
      $qb->execute();
    }
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
    if (method_exists($qb, 'executeStatement')) {
      $qb->executeStatement();
    } else {
      /**
       * @disregard P1007 Deprectated
       */
      $qb->execute();
    }
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
