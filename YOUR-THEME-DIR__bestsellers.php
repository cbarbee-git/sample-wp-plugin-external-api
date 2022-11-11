<?php
/*
* Template Name: Bestsellers
* Template Post Type: page
*/

get_header();
?>

    <main>

        <div class="content-container">

            <?php
            //go get the page using this template
            //only get one..the most recently published (?)
            //TODO: more work here to handle if multiple pages are tagged for this template
            $args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'orderby' => 'publish_date',
                'order' => 'DESC',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => '_wp_page_template',
                        'value' => 'bestsellers.php', // template name as stored in the dB
                    )
                )
            );
            $bestseller_page_query = new WP_Query($args);
            while ($bestseller_page_query->have_posts()) : $bestseller_page_query->the_post();
            //the_post();
            ?>
            <article>
                <header class="entry-header">
                    <?php
                    the_title( '<h1 class="entry-title">', '</h1>' );
                    ?>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <?php
                    //display the page content here.
                    the_content();
                    ?>
                </div>
                <?php
                //now go get and output the book content.
                global $wpdb;
                $sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nyt_books_table" );
                $results = $wpdb->get_results( $sql );
                ?>
                <div class="books">
                <?php
                foreach ($results as $result) {
                    ?>
                    <hr />
                    <div class="book">
                        <h5><?= $result->title ?></h5>
                        <p><?= $result->bookDescription ?></p>
                        <ul>
                            <li>Author: <?= $result->author ?></li>
                            <li>Contributor: <?= $result->contributor ?></li>
                            <li>Price: <?= $result->price ?></li>
                            <li>Contributors: <?= $result->contributors ?></li>
                            <li>Publisher: <?= $result->publisher ?></li>
                        </ul>
                    </div>
                    <?php
                }
                ?>
                </div>
            </article>
        </div>
        <?php
        endwhile; // End of the loop.
        ?>

        </div>

    </main>


<?php
get_footer();
