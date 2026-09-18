<?php
/** @var object $p */
if (!defined('ABSPATH')) exit;
$inbox = IB_Messages::inbox($p->id);
$pilots = IB_Player::all_active($p->id);
$to = isset($_GET['to']) ? absint($_GET['to']) : 0;
// Messages are highlighted as unread on this view, then marked read.
IB_Messages::mark_all_read($p->id);
?>
<div class="ib-panel">
    <h2>Sub-space Messages</h2>
    <?php if (!$inbox) : ?>
        <p class="ib-dim">Your inbox is empty.</p>
    <?php else : ?>
        <?php foreach ($inbox as $msg) : ?>
            <details class="ib-msg<?php echo (int) $msg->is_read ? '' : ' ib-unread'; ?>"<?php echo (int) $msg->is_read ? '' : ' open'; ?>>
                <summary>
                    <strong><?php echo esc_html($msg->subject); ?></strong>
                    <span class="ib-dim">from <?php echo esc_html(IB_Messages::sender_label($msg)); ?>, <?php echo esc_html(IB_UI::time_ago($msg->created_at)); ?></span>
                </summary>
                <div class="ib-msg-body"><?php echo nl2br(esc_html($msg->message_text)); ?></div>
                <p>
                    <?php if ((int) $msg->sender_player_id) : ?>
                        <a class="ib-btn ib-btn-small" href="<?php echo esc_url(IB_UI::url('messages', ['to' => $msg->sender_player_id])); ?>#ib-compose">Reply</a>
                    <?php endif; ?>
                    <?php echo IB_UI::button('delete_message', 'Delete', ['message_id' => $msg->id], 'ib-btn-small ib-btn-alt'); ?>
                </p>
            </details>
        <?php endforeach; ?>
        <p><?php echo IB_UI::button('delete_read', 'Delete all read messages', [], 'ib-btn-alt', 'Delete every message you have read?'); ?></p>
    <?php endif; ?>
</div>

<div class="ib-panel" id="ib-compose">
    <h3>Send a message</h3>
    <?php if (!$pilots) : ?>
        <p class="ib-dim">There are no other pilots yet.</p>
    <?php else : ?>
        <?php echo IB_UI::form_open('send_message', 'ib-stack'); ?>
            <label>To
                <select name="recipient_id" required>
                    <?php foreach ($pilots as $pilot) : ?>
                        <option value="<?php echo (int) $pilot->id; ?>" <?php selected($to, (int) $pilot->id); ?>><?php echo esc_html($pilot->alias_name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Subject <input type="text" name="subject" maxlength="120"></label>
            <label>Message <textarea name="message" rows="5" maxlength="5000" required></textarea></label>
            <button type="submit" class="ib-btn">Transmit</button>
        </form>
    <?php endif; ?>
</div>
