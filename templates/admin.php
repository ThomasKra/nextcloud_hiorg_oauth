<?php
/** @var array $_ */
/** @var \OCP\IL10N $l */

?>
<div id="hiorgoauth" class="section">
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
            <div>
                <input id="update_profile_on_login" type="checkbox" class="checkbox" name="update_profile_on_login" value="1" <?php p($_['update_profile_on_login'] ? 'checked' : '') ?> />
                <label for="update_profile_on_login"><?php p($l->t('Update user profile every login')) ?></label>
            </div>
        </p>
        <button><?php p($l->t('Save')); ?></button>
        <hr />

        <?php foreach ($_['providers'] as $name => $provider) : ?>
            <div class="provider-settings">
                <h2 class="provider-title"><img src="<?php print_unescaped(image_path('hiorg_oauth', strtolower($name) . '.svg')); ?>" /> <?php p(ucfirst($name)) ?></h2>
                <label>
                    <?php p($l->t('Client id')) ?><br>
                    <input type="text" name="providers[<?php p($name) ?>][appid]" value="<?php p($provider['appid']) ?>" />
                </label>
                <br />
                <label>
                    <?php p($l->t('Client Secret')) ?><br>
                    <input type="password" name="providers[<?php p($name) ?>][secret]" value="<?php p($provider['secret']) ?>" />
                </label>
                <br />
                <label>
                    <?php p($l->t('Default group')) ?><br>
                    <select name="providers[<?php p($name) ?>][defaultGroup]">
                        <option value=""><?php p($l->t('None')); ?></option>
                        <?php foreach ($_['groups'] as $group) : ?>
                            <option value="<?php p($group) ?>" <?php p($provider['defaultGroup'] === $group ? 'selected' : '') ?>>
                                <?php p($group) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </label>
                <?php if ($name === 'Hiorg') : ?>
                    <br />
                    <label>
                        <?php p($l->t('Quota')) ?><br>
                        <input type="text" name="providers[<?php p($name) ?>][quota]" id="quota" value="<?php p($provider['quota']); ?>" />
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
                            <select name="providers[<?php p($name) ?>][group_mapping][id_<?php p($num); ?>]">
                                <option value=""><?php p($l->t('None')); ?></option>
                                <?php
                                foreach ($_['groups'] as $group) {
                                    ?>
                                    <option value="<?php p($group); ?>" <?php p($provider['group_mapping']['id_' . $num] === $group ? 'selected' : '') ?>>
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
                <?php endif ?>
            </div>
        <?php endforeach ?>
        <br />
        <button><?php p($l->t('Save')); ?></button>
    </form>
</div>