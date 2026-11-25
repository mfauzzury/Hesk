<?php
global $hesk_settings, $hesklang;

/**
 * @var array $topArticles - Collection of top knowledgebase articles
 * @var array $latestArticles - Collection of newest/latest knowledgebase articles
 * @var array $serviceMessages - Collection of service messages to be displayed
 * @var array $messages - Collection of feedback messages to be displayed (such as "You have been logged out")
 * @var bool $accountRequired - `true` if an account is required to use the helpdesk, `false` otherwise
 * @var bool $customerLoggedIn - `true` if a customer is logged in, `false` otherwise
 * @var array $customerUserContext - User info for a customer if logged in.  `null` if a customer is not logged in.
 */
// This guard is used to ensure that users can't hit this outside of actual HESK code
if (!defined('IN_SCRIPT')) {
    die();
}
define('EXTRA_PAGE_CLASSES','page-index');

define('ALERTS',1);
define('KBSEARCH',1);
define('RATING',1);

define('OUTPUT_SEARCH_STYLING',1);
define('RENDER_COMMON_ELEMENTS',1);

define('OUTPUT_SEARCH_JAVASCRIPT',1);

global $BREADCRUMBS;
$BREADCRUMBS = array(
    array('url' => $hesk_settings['site_url'], 'title' => $hesk_settings['site_title']),
    array('title' => $hesk_settings['hesk_title'])
);

/* Print header */
require_once(TEMPLATE_PATH . 'customer/inc/header.inc.php');
?>
        <div class="main__content home">
            <div class="contr">
                <?php hesk3_show_messages($messages); ?>
            </div>

            <!-- Hero -->
            <section class="home-hero">
                <div class="contr">
                    <p class="home-hero__eyebrow"><?php echo $hesk_settings['hesk_title']; ?></p>
                    <h1 class="home-hero__title"><?php echo $hesklang['how_can_we_help']; ?></h1>
                    <p class="home-hero__sub">Search our knowledge base for quick answers, or open a ticket and our team will get back to you.</p>
                    <div class="home-hero__search">
                        <?php displayKbSearch(); ?>
                    </div>
                </div>
            </section>

            <div class="contr">
                <?php hesk3_show_messages($serviceMessages); ?>

                <!-- Quick actions -->
                <div class="home-actions">
                    <a href="index.php?a=add" class="home-card">
                        <span class="icon-in-circle" aria-hidden="true">
                            <svg class="icon icon-submit-ticket">
                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-submit-ticket"></use>
                            </svg>
                        </span>
                        <span class="home-card__text">
                            <span class="home-card__title"><?php echo $hesklang['submit_ticket']; ?></span>
                            <span class="home-card__descr"><?php echo $hesklang['open_ticket']; ?></span>
                        </span>
                        <span class="home-card__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                    <?php if ($accountRequired || $customerLoggedIn): ?>
                    <a href="my_tickets.php" class="home-card">
                        <span class="icon-in-circle" aria-hidden="true">
                            <svg class="icon icon-document">
                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-document"></use>
                            </svg>
                        </span>
                        <span class="home-card__text">
                            <span class="home-card__title"><?php echo $hesklang['customer_my_tickets_heading']; ?></span>
                            <span class="home-card__descr"><?php echo $hesklang['customer_my_tickets_description']; ?></span>
                        </span>
                        <span class="home-card__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                    <?php else: ?>
                    <a href="ticket.php" class="home-card">
                        <span class="icon-in-circle" aria-hidden="true">
                            <svg class="icon icon-document">
                                <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-document"></use>
                            </svg>
                        </span>
                        <span class="home-card__text">
                            <span class="home-card__title"><?php echo $hesklang['view_existing_tickets']; ?></span>
                            <span class="home-card__descr"><?php echo $hesklang['vet']; ?></span>
                        </span>
                        <span class="home-card__arrow" aria-hidden="true">&rarr;</span>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if (!$accountRequired && !$customerLoggedIn): ?>
                <!-- Track a ticket -->
                <section class="home-track">
                    <div class="home-track__intro">
                        <h2 class="home-track__title">Already have a ticket?</h2>
                        <p class="home-track__descr">Enter your tracking ID<?php echo $hesk_settings['email_view_ticket'] ? ' and email' : ''; ?> to see the latest update.</p>
                    </div>
                    <form action="ticket.php" method="get" class="home-track__form">
                        <label class="screen-reader-text" for="home-track-id"><?php echo $hesklang['ticket_trackID']; ?></label>
                        <input type="text" id="home-track-id" name="track" maxlength="20" class="form-control" placeholder="<?php echo $hesklang['ticket_trackID']; ?>" required>
                        <?php if ($hesk_settings['email_view_ticket']): ?>
                        <label class="screen-reader-text" for="home-track-email"><?php echo $hesklang['email']; ?></label>
                        <input type="email" id="home-track-email" name="e" class="form-control" placeholder="<?php echo $hesklang['email']; ?>" <?php echo $hesk_settings['require_email'] ? 'required' : ''; ?>>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-full"><?php echo $hesklang['view_ticket']; ?></button>
                    </form>
                </section>
                <?php endif; ?>

                <!-- How it works -->
                <section class="home-steps" aria-label="How it works">
                    <h2 class="home-section-title">How it works</h2>
                    <ol class="home-steps__list">
                        <li class="home-step">
                            <span class="home-step__num">01</span>
                            <h3 class="home-step__title">Describe your issue</h3>
                            <p class="home-step__descr">Tell us what is going on using the ticket form. It only takes a minute.</p>
                        </li>
                        <li class="home-step">
                            <span class="home-step__num">02</span>
                            <h3 class="home-step__title">We get to work</h3>
                            <p class="home-step__descr">Our team reviews your request and replies to you by email.</p>
                        </li>
                        <li class="home-step">
                            <span class="home-step__num">03</span>
                            <h3 class="home-step__title">Track and resolve</h3>
                            <p class="home-step__descr">Follow progress any time with your tracking ID until it is resolved.</p>
                        </li>
                    </ol>
                </section>

                <?php if ($hesk_settings['kb_enable']): ?>
                <article class="article">
                    <h2 class="article__heading">
                        <a href="knowledgebase.php">
                            <span class="icon-in-circle" aria-hidden="true">
                                <svg class="icon icon-knowledge">
                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-knowledge"></use>
                                </svg>
                            </span>
                            <span><?php echo $hesklang['kb_text']; ?></span>
                        </a>
                    </h2>
                    <div class="tabbed__head">
                        <ul class="tabbed__head_tabs">
                            <?php
                            if (count($topArticles) > 0):
                            ?>
                            <li class="current" data-link="tab1">
                                <span><?php echo $hesklang['popart']; ?></span>
                            </li>
                            <?php
                            endif;
                            if (count($latestArticles) > 0):
                            ?>
                            <li data-link="tab2">
                                <span><?php echo $hesklang['latart']; ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="tabbed__tabs">
                        <?php if (count($topArticles) > 0): ?>
                        <div class="tabbed__tabs_tab is-visible" data-tab="tab1">
                            <?php foreach ($topArticles as $article): ?>
                            <a href="knowledgebase.php?article=<?php echo $article['id']; ?>" class="preview">
                                <span class="icon-in-circle" aria-hidden="true">
                                    <svg class="icon icon-knowledge">
                                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-knowledge"></use>
                                    </svg>
                                </span>
                                <div class="preview__text">
                                    <h3 class="preview__title"><?php echo $article['subject'] ?></h3>
                                    <p>
                                        <span class="lightgrey"><?php echo $hesklang['kb_cat']; ?>:</span>
                                        <span class="ml-1"><?php echo $article['category']; ?></span>
                                    </p>
                                    <p class="navlink__descr">
                                        <?php echo $article['content_preview']; ?>
                                    </p>
                                </div>
                                <?php if ($hesk_settings['kb_views'] || $hesk_settings['kb_rating']): ?>
                                    <div class="rate">
                                        <?php if ($hesk_settings['kb_views']): ?>
                                            <div style="margin-right: 10px; display: -ms-flexbox; display: flex;">
                                                <svg class="icon icon-eye-close">
                                                    <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-eye-close"></use>
                                                </svg>
                                                <span class="lightgrey"><?php echo $article['views_formatted']; ?></span>
                                            </div>
                                        <?php
                                        endif;
                                        if ($hesk_settings['kb_rating']): ?>
                                            <?php echo hesk3_get_customer_rating($article['rating']); ?>
                                            <?php if ($hesk_settings['kb_views']) echo '<span class="lightgrey">('.$article['votes_formatted'].')</span>'; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <!--[if IE]>
                                <p>&nbsp;</p>
                            <![endif]-->
                            <?php endforeach; ?>
                        </div>
                        <?php
                        endif;
                        if (count($latestArticles) > 0):
                        ?>
                        <div class="tabbed__tabs_tab <?php echo count($topArticles) === 0 ? 'is-visible' : ''; ?>" data-tab="tab2">
                            <?php foreach ($latestArticles as $article): ?>
                                <a href="knowledgebase.php?article=<?php echo $article['id']; ?>" class="preview">
                                    <span class="icon-in-circle" aria-hidden="true">
                                        <svg class="icon icon-knowledge">
                                            <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-knowledge"></use>
                                        </svg>
                                    </span>
                                    <div class="preview__text">
                                        <h3 class="preview__title"><?php echo $article['subject'] ?></h3>
                                        <p>
                                            <span class="lightgrey"><?php echo $hesklang['kb_cat']; ?>:</span>
                                            <span class="ml-1"><?php echo $article['category']; ?></span>
                                        </p>
                                        <p class="navlink__descr">
                                            <?php echo $article['content_preview']; ?>
                                        </p>
                                    </div>
                                    <?php if ($hesk_settings['kb_views'] || $hesk_settings['kb_rating']): ?>
                                        <div class="rate">
                                            <?php if ($hesk_settings['kb_views']): ?>
                                                <div style="margin-right: 10px; display: -ms-flexbox; display: flex;">
                                                    <svg class="icon icon-eye-close">
                                                        <use xlink:href="<?php echo TEMPLATE_PATH; ?>customer/img/sprite.svg#icon-eye-close"></use>
                                                    </svg>
                                                    <span class="lightgrey"><?php echo $article['views_formatted']; ?></span>
                                                </div>
                                            <?php
                                            endif;
                                            if ($hesk_settings['kb_rating']): ?>
                                                <?php echo hesk3_get_customer_rating($article['rating']); ?>
                                                <?php if ($hesk_settings['kb_views']) echo '<span class="lightgrey">('.$article['votes_formatted'].')</span>'; ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <!--[if IE]>
                                    <p>&nbsp;</p>
                                <![endif]-->
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="article__footer">
                        <a href="knowledgebase.php" class="btn btn--blue-border" ripple="ripple"><?php echo $hesklang['viewkb']; ?></a>
                    </div>
                </article>
                <?php
                endif;
                if (!$customerLoggedIn && $hesk_settings['alink']):
                ?>
                <div class="article__footer">
                    <a href="<?php echo $hesk_settings['admin_dir']; ?>/" class="link"><?php echo $hesklang['ap']; ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>
<?php
/* Print Footer */
require_once(TEMPLATE_PATH . 'customer/inc/footer.inc.php');
?>
