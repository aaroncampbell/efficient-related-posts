=== Efficient Related Posts ===
Contributors: aaroncampbell
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal%40xavisys%2ecom&item_name=Efficient%20Related%20Posts&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: related posts, posts, related, seo
Requires at least: 2.7
Tested up to: 2.8.1
Stable tag: 0.2.3

A related posts plugin that works quickly even with thousands of posts and tags. Requires PHP5.

== Description ==

There is a <a href="http://wpinformer.com/problem-related-post-plugins/">problem
with related posts plugins</a>, and Efficient Related Posts is fixing that by
approaching the problem from a different direction and offering a very different
solution.

Basically, current related post plugins build the list of related posts on the
fly when the user needs to view it.  Since blogs tend to be viewed far more
often than they are updated (often hundreds of times more often), these queries
are run way more times than they need to be.  This not only wastes CPU cycles,
but if the queries are slow (which they will be if you have 1000s of posts and
tags) then the user gets a poor experience from slow page loads.

Efficient Related Posts moves all this effort into the admin section, finding
related posts when a post is saved rather than when the user views it.  The
advantage is that if the query is slow it happens less often and the post writer
is the one that waits rather than the user (which I think is WAY better).

There are limitations.  For example, since the related posts are stored as post
meta data, we only store a certain number of them (10 by default, but you can
set it to whatever you want).  This means that if you decide you need to display
more than 10, you need to have the plugin re-process all posts.  I generally
display up to 5 related posts, but store 10 just in case I decide to display
more in some areas.  Also, since the related posts are calculated when a post is
saved, manually adding a tag through the database will have no effect on the
related posts, although I recommend not doing that anyway.

Requires PHP5.

You may also be interested in <a href="http://wpinformer.com">WordPress tips and tricks at WordPress Informer</a> or general <a href="http://webdevnews.net">Web Developer News</a>

== Installation ==

1. Verify that you have PHP5, which is required for this plugin.
1. Upload the whole `efficient-related-posts` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= If it calculates related posts when a post is saved, won't a post only be related to older posts? =

No, Efficient Related Posts finds all the posts related to the one being saved,
and if the current post is more closely related to one of those posts than the
least related post that is currently stored, it re-processes that post.  Simple
right?  Well, maybe it's not so simple, but rest assured that your posts can and
will show the posts they are most related to regardless of post date.

= What metrics are used? =

Posts are considered related based on tags.  This may be extended in the future,
but I wanted to keep the queries as clean as possible.
