<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */


?>
<div id="hiorgoauth" class="section">
  <p>
    Callback-URL: <pre><?php print_unescaped($_['callback_url']) ?></pre>
  </p>
    <form id="hiorgoauth_settings" action="<?php print_unescaped($_['action_url']) ?>" method="post">

        <p>
            <div>
                <input id="disable_registration" type="checkbox" class="checkbox" name="disable_registration" value="1" <?php p($_['disable_registration'] ? 'checked' : '') ?> />
                <label for="disable_registration"><?php p($l->t('Disable auto create new users')) ?></label>
            </div>
            <div>
                <input id="allow_login_connect" type="checkbox" class="checkbox" name="allow_login_connect" value="1" <?php p($_['allow_login_connect'] ? 'checked' : '') ?> />
                <label for="allow_login_connect"><?php p($l->t('Allow users to connect hiorg oauths with their account')) ?></label>
            </div>
            <div>
                <input id="prevent_create_email_exists" type="checkbox" class="checkbox" name="prevent_create_email_exists" value="1" <?php p($_['prevent_create_email_exists'] ? 'checked' : '') ?> />
                <label for="prevent_create_email_exists"><?php p($l->t('Prevent creating an account if the email address exists in another account')) ?></label>
            </div>
        </p>
        <button><?php p($l->t('Save')); ?></button>
        <hr />

            <div class="provider-settings">
                <h2 class="provider-title"><img src="<?php print_unescaped(image_path('hiorg_oauth', strtolower('Hiorg') . '.svg')); ?>" /> <?php p(ucfirst('Hiorg')) ?></h2>
                <label>
                    <?php p($l->t('API Version')) ?><br>
                    <select name="hiorgSettings[api_version]">
                      <?php
                      foreach ($_['versions'] as $version => $version_str) {
                      ?>
                        <option value="<?php p($version); ?>" <?php p($_['hiorgSettings']['api_version'] === $version ? 'selected' : '') ?>><?php p($version_str); ?></option>
                      <?php
                      }
                      ?>
                  </select>
                </label>
                <br />
                <label>
                    <?php p($l->t('Client id')) ?><br>
                    <input type="text" name="hiorgSettings[appid]" value="<?php p($_['hiorgSettings']['appid']) ?>" />
                </label>
                <br />
                <label>
                    <?php p($l->t('Client Secret')) ?><br>
                    <input type="password" name="hiorgSettings[secret]" value="<?php p($_['hiorgSettings']['secret']) ?>" />
                </label>
                <br />
                <label>
                    <?php p($l->t('HiOrg Org.-Kürzel')) ?><br>
                    <input type="text" name="hiorgSettings[orga]" value="<?php p($_['hiorgSettings']['orga']) ?>" />
                </label>
                <br />
                <label>
                    <?php p($l->t('Default group')) ?><br>
                    <select name="hiorgSettings[defaultGroup]">
                        <option value=""><?php p($l->t('None')); ?></option>
                        <?php foreach ($_['groups'] as $group) : ?>
                            <option value="<?php p($group) ?>" <?php p($_['hiorgSettings']['defaultGroup'] === $group ? 'selected' : '') ?>>
                                <?php p($group) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </label>
                    <br />
                    <label>
                        <?php p($l->t('Quota')) ?><br>
                        <input type="text" name="hiorgSettings[quota]" id="quota" value="<?php p($_['hiorgSettings']['quota']); ?>" />
                        <p>
                            <em>
                                Standard-Quota für Benutzer vom HiOrg-Server. z.B.: 10 MB (Standard-Abkürzungen wie MB, GB verwenden)
                            </em>
                        </p>
                    </label>
                    <h3><?php p($l->t('Groups')) ?></h3>
                    <?php
                            $num = 0;
                            $value_name = 'group_id_' . $num;
                            ?>
                    <?php
                            for ($i = 0; $i < 11; $i++) {
                                $num = 2 ** $i;
                                $value_name = 'group_id_' . $num;
                                ?>
                        <br />
                        <label>Group ID <?php p($num); ?>
                            <select name="hiorgSettings[group_mapping][id_<?php p($num); ?>]">
                                <option value=""><?php p($l->t('None')); ?></option>
                                <?php
                                            foreach ($_['groups'] as $group) {
                                                ?>
                                    <option value="<?php p($group); ?>" <?php p($_['hiorgSettings']['group_mapping']['id_' . $num] === $group ? 'selected' : '') ?>>
                                        <?php p($group); ?>
                                    </option>
                                <?php
                                            }
                                            ?>
                            </select>
                        </label>
                    <?php
                            }
                            ?>
            </div>
        <br />
        <button><?php p($l->t('Save')); ?></button>
    </form>
</div>