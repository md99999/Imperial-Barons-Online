<?php
if (!defined('ABSPATH')) exit;

/**
 * Private sub-space messages between pilots, plus system notices (sender 0).
 */
class IB_Messages {

    public static function send($p, $recipient_id, $subject, $text) {
        global $wpdb;
        $recipient = IB_Player::get($recipient_id);
        if (!$recipient) throw new IB_Game_Exception('Choose a pilot to send to.');
        if ((int) $recipient->id === (int) $p->id) throw new IB_Game_Exception('Talking to yourself again?');
        $subject = trim(sanitize_text_field($subject));
        $text = trim(sanitize_textarea_field($text));
        if ($text === '') throw new IB_Game_Exception('Write a message first.');
        $wpdb->insert(IB_DB::t('messages'), [
            'sender_player_id' => $p->id, 'recipient_player_id' => $recipient->id, 'team_id' => 0,
            'subject' => substr($subject !== '' ? $subject : '(no subject)', 0, 120),
            'message_text' => $text, 'created_at' => current_time('mysql'),
        ]);
        return sprintf('Message transmitted to %s.', $recipient->alias_name);
    }

    public static function system($recipient_id, $subject, $text) {
        global $wpdb;
        $wpdb->insert(IB_DB::t('messages'), [
            'sender_player_id' => 0, 'recipient_player_id' => $recipient_id, 'team_id' => 0,
            'subject' => substr($subject, 0, 120), 'message_text' => $text, 'created_at' => current_time('mysql'),
        ]);
    }

    public static function inbox($player_id, $limit = 100) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . IB_DB::t('messages') . ' WHERE recipient_player_id = %d ORDER BY id DESC LIMIT %d', $player_id, $limit
        ));
    }

    public static function unread_count($player_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . IB_DB::t('messages') . ' WHERE recipient_player_id = %d AND is_read = 0', $player_id
        ));
    }

    public static function mark_all_read($player_id) {
        global $wpdb;
        $wpdb->update(IB_DB::t('messages'), ['is_read' => 1], ['recipient_player_id' => $player_id, 'is_read' => 0]);
    }

    public static function delete($p, $message_id) {
        global $wpdb;
        $wpdb->delete(IB_DB::t('messages'), ['id' => (int) $message_id, 'recipient_player_id' => $p->id]);
        return 'Message deleted.';
    }

    public static function delete_read($p) {
        global $wpdb;
        $wpdb->delete(IB_DB::t('messages'), ['recipient_player_id' => $p->id, 'is_read' => 1]);
        return 'All read messages deleted.';
    }

    public static function sender_label($msg) {
        return (int) $msg->sender_player_id ? IB_Player::name($msg->sender_player_id) : 'The Imperial Chancery';
    }

    public static function news($limit = 15) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . IB_DB::t('news') . ' ORDER BY id DESC LIMIT %d', $limit));
    }
}
