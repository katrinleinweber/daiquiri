<?php
/*
 *  Copyright (c) 2012, 2013 Jochen S. Klar <jklar@aip.de>,
 *                           Adrian M. Partl <apartl@aip.de>, 
 *                           AIP E-Science (www.aip.de)
 *
 *  Licensed under the Apache License, Version 2.0 (the "License");
 *  you may not use this file except in compliance with the License.
 *  See the NOTICE file distributed with this work for additional
 *  information regarding copyright ownership. You may obtain a copy
 *  of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *  Unless required by applicable law or agreed to in writing, software
 *  distributed under the License is distributed on an "AS IS" BASIS,
 *  WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *  See the License for the specific language governing permissions and
 *  limitations under the License.
 */
?>

<?php get_header(); ?>

<div id="wp-content" class="row">
    <div class="span9 main">
        <h2>
            Search results for "<?php 
            echo $_GET['s'];
            ?>".
        </h2>
        <p>
            There are <?php 
            global $wp_query;
            echo $wp_query->found_posts;
            ?> page(s) matching the search query.
        </p>        

        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
            <div class="post">
                <h3>
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>

                <p>
                    <?php 
                    $content = get_the_content();
                    $trimmed_content = wp_trim_words($content, 40, '<a href="'. get_permalink() .'"> ... more</a>' );
                    echo $trimmed_content;
                    ?>
                </p>
            </div>

            <p align="center"><?php posts_nav_link(); ?></p>
        <?php endwhile; else: ?>
            <p>Sorry, no page found.</p>
        <?php endif; ?>
    </div>
    <div class="span3 sidebar">
        <?php get_sidebar(); ?>
    </div>
</div>   

<?php get_footer(); ?>