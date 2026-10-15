<?php
/**
 * Remote Vault: the balances of every Vault the pilot owns, and a way to draw on them from
 * anywhere in the galaxy, so the money a colony earns can be spent at whatever port the pilot
 * happens to be standing in.
 *
 * @var object $p
 */
if (!defined('ABSPATH')) exit;

$ib_vaults = IB_Planets::vaults_of($p->id);
$ib_fee = max(0, (int) IB_Settings::get('vault_transfer_fee_percent'));
$ib_total = 0;
foreach ($ib_vaults as $ib_v) $ib_total += (int) $ib_v->bastion_vault;
?>
<div class="ib-panel">
    <h3>Remote Vault</h3>
    <?php if (!$ib_vaults) : ?>
        <p class="ib-dim">You have no Vaults. A bastion on a world you own gives it one, and the Crown's factors
            pay the proceeds of that world's daily sale straight into it.</p>
    <?php else : ?>
        <p class="ib-small">Your Vaults hold <strong><?php echo IB_Game::fmt($ib_total); ?></strong> credits in all.
            You can draw on them from anywhere<?php if ($ib_fee) : ?>, less a
            <?php echo (int) $ib_fee; ?>% courier fee<?php endif; ?>, without spending a turn.
            Landing on the world itself lets you deposit and withdraw with no fee at all.</p>
        <div class="ib-table-wrap">
        <table class="ib-table">
            <thead><tr><th>World</th><th>Sector</th><th>Bastion</th><th>Vault</th><th>Draw</th></tr></thead>
            <tbody>
            <?php foreach ($ib_vaults as $ib_v) :
                $ib_held = (int) $ib_v->bastion_vault;
                $ib_net = $ib_held - (int) ceil($ib_held * $ib_fee / 100); ?>
                <tr>
                    <td><?php echo esc_html($ib_v->planet_name); ?>
                        <span class="ib-dim ib-small">(<?php echo esc_html(IB_Planets::class_name($ib_v->planet_class)); ?>)</span></td>
                    <td><?php echo IB_UI::fly_to_planet($p, $ib_v); ?></td>
                    <td>L<?php echo (int) $ib_v->bastion_level; ?></td>
                    <td><?php echo IB_Game::fmt($ib_held); ?></td>
                    <td>
                        <?php if ($ib_held > 0) : ?>
                            <?php echo IB_UI::form_open('vault_draw', 'ib-inline'); ?>
                                <input type="hidden" name="planet_id" value="<?php echo (int) $ib_v->id; ?>">
                                <input type="number" name="amount" min="1" max="<?php echo $ib_held; ?>"
                                       value="<?php echo $ib_held; ?>" class="ib-num" aria-label="Credits to draw">
                                <button type="submit" class="ib-btn ib-btn-small">Draw</button>
                            </form>
                            <?php if ($ib_fee) : ?>
                                <span class="ib-dim ib-small">all of it nets <?php echo IB_Game::fmt($ib_net); ?></span>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="ib-dim ib-small">empty</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>
