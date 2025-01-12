<?php
declare(strict_types=1);

namespace NewsApiPlugin\Services;

class WPNewsPublisherService
{
    private string $contentDivider = '<br>';

    public function publishNews(array $articles,
                                int $categoryId,
                                array $tagIds,
                                string $postStatus,
                                string $postType
    ): array {
        $publishedPosts = [];

        foreach ($articles as $article) {

           if ($this->postExists($article, $postType)) {
               continue;
           }

            $content = '';
            if (!empty($article['description'])) {
                $content .= $article['description'] . $this->contentDivider;
            }

            // Combine 'fullContent' and 'content' with a divider if both are present
            $content .= !empty($article['fullContent']) && !empty($article['content'])
                ? $article['fullContent'] . $this->contentDivider . $article['content']
                : (!empty($article['fullContent']) ? $article['fullContent'] : $article['content']);

            $link = '';
            if (!empty($article['link'])) {
                if (is_string($article['link'])) {
                    $link = $article['link'];
                } elseif (is_array($article['link']) && isset($article['link'][0])) {
                    $link = $article['link'][0];
                }

                $content .= '<br>' . json_encode(['source' => $link]);
            }

            // Insert the post into WordPress
            $postId = wp_insert_post([
                'post_title'   => sanitize_text_field($article['title']),
                'post_content' => wp_kses_post(wp_strip_all_tags($content)),
                'post_status'  => $postStatus ?? 'publish',
                'post_type'    => $postType,
                'post_category'=> $postType === 'post' ? [$categoryId] : []
            ]);

            add_post_meta($postId, 'read_status', 'unread', true);

            if (!is_wp_error($postId) && $postId) {
                // Assign tags if post type is 'post'
                if ($postType === 'post' && !empty($tagIds)) {
                    wp_set_post_terms($postId, $tagIds, 'post_tag');
                }

                // Add custom fields
                if (!empty($article['link'])) {
                    add_post_meta($postId, 'source_link', json_encode($article['link']), true);
                }
                if (!empty($article['pubDate'])) {
                    add_post_meta($postId, 'publication_date', json_encode($article['pubDate']), true);
                }
                if (!empty($article['imageUrl'])) {
                    add_post_meta($postId, 'image_url', json_encode($article['imageUrl']), true);
                }

                $publishedPosts[] = $postId;
            }
        }

        return [
            'count' => count($publishedPosts),
            'post_ids' => $publishedPosts,
        ];
    }

    protected function postExists(array $article, string $postType): bool
    {
        $query = new \WP_Query(array(
            'post_type' => $postType,
            'meta_query' => array(
                array(
                    'key' => 'source_link',
                    'value' => stripslashes(json_encode($article['link'])),
                )
            ),
            'posts_per_page' => 1
        ));

        return $query->have_posts();
    }

}
