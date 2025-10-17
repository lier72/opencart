<?php if(!empty($articles)):?> 

    <?php $index = 1; ?>
    <?php foreach($articles as $article):?>

        <div class="post">

            <div class="date-wrapper">
                <div class="date-published">
                    <span class="month">
                        <?php echo date('M', strtotime($article['date_published'])); ?>
                    </span>
                    <span class="day">
                        <?php echo date('d', strtotime($article['date_published'])); ?>
                    </span>
                </div>
            </div>
            <div class="post-content">
                <?php if(!empty($article['gallery'])):?>
                    <?php if($article['article_list_gallery_display'] == 'CLASSIC'):?>
                        <div class="post-media">
                            <?php echo $article['gallery'][0]['output'] ?>
                        </div>
                    <?php endif; ?>
                    <?php if($article['article_list_gallery_display'] == 'SLIDER'):?>
                        <div class="post-media">
                            <div class="media-slider">
                            <?php foreach($article['gallery'] as $gallery):?>
                                <div class="item"><?php echo $gallery['output'] ?></div>
                            <?php endforeach ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <h2 class="post-title">
                <?php echo $article['title'] ?>
                </h2>
                <ul class="meta">
                    <?php if(!empty($article['author'])):?>
                    <li><?php echo $text_posted_by ?> : <a href="<?php echo $article['author']['href']; ?>"><?php echo $article['author']['name']; ?></a></li>
                    <?php endif; ?>
                    <?php if(!empty($article['categories'])):?>
                    <?php $i = 0; ?>
                    <li class="post-categories">
                        <span><?php echo $text_category ?>: </span>
                        <?php foreach($article['categories'] as $cat):?>
                        <?php $i++; ?>
                        <a href="<?php echo $cat['href'] ?>"><?php echo $cat['name'] ?></a><?php if($i < count($article['categories'])):?>, <?php endif; ?>
                        <?php endforeach; ?>
                    </li>
                    <?php endif; ?>
                    <?php if($settings['comments_engine'] == 'LOCAL'):?>
                    <li><?php echo $text_comments?>: <?php echo $article['comments_count']?></li>
                    <?php endif; ?>
                </ul>
                <div class="post-description">
                    <?php echo $article['description']?>
                </div>
                <a href="<?php echo $article['href'] ?>" class="button more"><?php echo $button_read_more ?></a>
            </div>       
        </div>

        
    <?php $index++; ?>
    <?php endforeach; ?>



<?php endif; ?>
