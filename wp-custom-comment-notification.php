<?php
/*
Plugin Name: WP Custom Notification 
Plugin URI: http://dgrebb.com/
Description: Customizes comment notifications to authors.
Author: dgrebb
Author URI: http://dgrebb.com/
Version: 1.0
License: MIT License - http://www.opensource.org/licenses/mit-license.php

Permission is hereby granted, free of charge, to any person obtaining a copy of this
software and associated documentation files (the "Software"), to deal in the Software
without restriction, including without limitation the rights to use, copy, modify, merge,
publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons
to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or
substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

if ( ! function_exists('wp_notify_postauthor') ) {
/**
 * Notify an author of a comment/trackback/pingback to one of their posts.
 *
 * @since 1.0.0
 *
 * @param int $comment_id Comment ID
 * @param string $comment_type Optional. The comment type either 'comment' (default), 'trackback', or 'pingback'
 * @return bool False if user email does not exist. True on completion.
 */
function wp_notify_postauthor( $comment_id, $comment_type = '' ) {
    $comment = get_comment( $comment_id );
    $post    = get_post( $comment->comment_post_ID );
    $author  = get_userdata( $post->post_author );

    // The comment was left by the author
    if ( $comment->user_id == $post->post_author )
        return false;

    // The author moderated a comment on his own post
    if ( $post->post_author == get_current_user_id() )
        return false;

    // If there's no email to send the comment to
    if ( '' == $author->user_email )
        return false;

    $comment_author_domain = @gethostbyaddr($comment->comment_author_IP);

    // The blogname option is escaped with esc_html on the way into the database in sanitize_option
    // we want to reverse this for the plain text arena of emails.
    $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

    if ( empty( $comment_type ) ) $comment_type = 'comment';

    if ('comment' == $comment_type) {
        $notify_message  = sprintf( __( 'New comment on your post "%s"' ), $post->post_title ) . "\r\n";
        /* translators: 1: comment author, 2: author IP, 3: author domain */
        $notify_message .= sprintf( __('Author : %1$s'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
        $notify_message .= sprintf( __('E-mail : %s'), $comment->comment_author_email ) . "\r\n";
        $notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "\r\n";
        $notify_message .= __('Comment: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
        $notify_message .= __('You can see all comments on this post here: ') . "\r\n";
        /* translators: 1: blog name, 2: post title */
        $subject = sprintf( __('[%1$s] Comment: "%2$s"'), $blogname, $post->post_title );
    } elseif ('trackback' == $comment_type) {
        $notify_message  = sprintf( __( 'New trackback on your post "%s"' ), $post->post_title ) . "\r\n";
        /* translators: 1: website name, 2: author IP, 3: author domain */
        $notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
        $notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "\r\n";
        $notify_message .= __('Excerpt: ') . "\r\n" . $comment->comment_content . "\r\n\r\n";
        $notify_message .= __('You can see all trackbacks on this post here: ') . "\r\n";
        /* translators: 1: blog name, 2: post title */
        $subject = sprintf( __('[%1$s] Trackback: "%2$s"'), $blogname, $post->post_title );
    } elseif ('pingback' == $comment_type) {
        $notify_message  = sprintf( __( 'New pingback on your post "%s"' ), $post->post_title ) . "\r\n";
        /* translators: 1: comment author, 2: author IP, 3: author domain */
        $notify_message .= sprintf( __('Website: %1$s (IP: %2$s , %3$s)'), $comment->comment_author, $comment->comment_author_IP, $comment_author_domain ) . "\r\n";
        $notify_message .= sprintf( __('URL    : %s'), $comment->comment_author_url ) . "\r\n";
        $notify_message .= __('Excerpt: ') . "\r\n" . sprintf('[...] %s [...]', $comment->comment_content ) . "\r\n\r\n";
        $notify_message .= __('You can see all pingbacks on this post here: ') . "\r\n";
        /* translators: 1: blog name, 2: post title */
        $subject = sprintf( __('[%1$s] Pingback: "%2$s"'), $blogname, $post->post_title );
    }
    $notify_message .= get_permalink($comment->comment_post_ID) . "#comments\r\n\r\n";
    $notify_message .= sprintf( __('Permalink: %s'), get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment_id ) . "\r\n";
    if ( EMPTY_TRASH_DAYS )
        $notify_message .= sprintf( __('Trash it: %s'), admin_url("comment.php?action=trash&c=$comment_id") ) . "\r\n";
    else
        $notify_message .= sprintf( __('Delete it: %s'), admin_url("comment.php?action=delete&c=$comment_id") ) . "\r\n";
    $notify_message .= sprintf( __('Spam it: %s'), admin_url("comment.php?action=spam&c=$comment_id") ) . "\r\n";

    $wp_email = 'wordpress@' . preg_replace('#^www\.#', '', strtolower($_SERVER['SERVER_NAME']));

    if ( '' == $comment->comment_author ) {
        $from = "From: \"$blogname\" <$wp_email>";
        if ( '' != $comment->comment_author_email )
            $reply_to = "Reply-To: $comment->comment_author_email";
    } else {
        $from = "From: \"$comment->comment_author\" <$wp_email>";
        if ( '' != $comment->comment_author_email )
            $reply_to = "Reply-To: \"$comment->comment_author_email\" <$comment->comment_author_email>";
    }

    $message_headers = "$from\n"
        . "Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"\n";

    if ( isset($reply_to) )
        $message_headers .= $reply_to . "\n";

    $notify_message = apply_filters('comment_notification_text', $notify_message, $comment_id);
    $subject = apply_filters('comment_notification_subject', $subject, $comment_id);
    $message_headers = apply_filters('comment_notification_headers', $message_headers, $comment_id);

    @wp_mail( $author->user_email, $subject, $notify_message, $message_headers );

    return true;
}
}