<?php

declare(strict_types=1);

namespace NewsApiPlugin\Admin;

class PubMedMetaBox
{
    public static function register(): void
    {
        add_action('add_meta_boxes', [self::class, 'addMetaBox']);
        add_action('save_post_newsapi_pubmed', [self::class, 'saveMetaBox']);
    }

    public static function addMetaBox(): void
    {
        add_meta_box(
            'newsapi_pubmed_details',
            'PubMed Details',
            [self::class, 'renderMetaBox'],
            'newsapi_pubmed',
            'normal',
            'high'
        );
    }

    public static function renderMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('newsapi_pubmed_details_nonce', 'newsapi_pubmed_details_nonce_field');

        // Retrieve saved meta values
        $categoryId = (int) get_post_meta($post->ID, '_pubmed_category', true);
        $tags = (array) get_post_meta($post->ID, '_pubmed_tags', true);
        $keywords = get_post_meta($post->ID, '_pubmed_keywords', true) ?: '';
        $maxSteps = (int) get_post_meta($post->ID, '_pubmed_max_steps', true);
        $maxArticlesPerStep = (int) get_post_meta($post->ID, '_pubmed_max_articles_per_step', true);
        $post_status = get_post_meta($post->ID, '_pubmed_post_status', true);
        $mindate = get_post_meta($post->ID, '_pubmed_mindate', true);
        $maxdate = get_post_meta($post->ID, '_pubmed_maxdate', true);
        $datetype = get_post_meta($post->ID, '_pubmed_datetype', true);
        $pt = get_post_meta($post->ID, '_pubmed_pt', true);
        $la = get_post_meta($post->ID, '_pubmed_la', true);
        $fields = (array) get_post_meta($post->ID, '_pubmed_fields', true);

        // Category Selector
        $categories = get_categories(['hide_empty' => false]);
        echo '<p><label>Category:</label><br>';
        echo '<select name="pubmed_category">';
        echo '<option value="">-- Select Category --</option>';
        foreach ($categories as $cat) {
            $selected = $cat->term_id === $categoryId ? 'selected' : '';
            echo '<option value="' . (int)$cat->term_id . '" ' . $selected . '>' . esc_html($cat->name) . '</option>';
        }
        echo '</select></p>';

        // Tags (checkboxes in a scrollable area)
        $all_tags = get_tags(['hide_empty' => false]);
        echo '<p><label>Tags:</label><br>';
        echo '<div style="max-height: 200px; overflow:auto; border:1px solid #ccc; padding:5px;">';
        foreach ($all_tags as $t) {
            $checked = in_array($t->term_id, $tags) ? 'checked' : '';
            echo '<label style="display:block;"><input type="checkbox" name="pubmed_tags[]" value="' . (int)$t->term_id . '" ' . $checked . '> ' . esc_html($t->name) . '</label>';
        }
        echo '</div></p>';

        // Keywords
        echo '<p><label>Keywords (comma separated):</label><br>';
        echo '<input type="text" name="pubmed_keywords" value="' . esc_attr($keywords) . '" class="regular-text"></p>';

        // Max Steps Deep
        echo '<p><label>Max Steps Deep:</label><br>';
        echo '<input type="number" name="pubmed_max_steps" value="' . esc_attr($maxSteps) . '" min="1"></p>';

        // Max Articles per Step
        echo '<p><label>Max Articles per Step:</label><br>';
        echo '<input type="number" name="pubmed_max_articles_per_step" value="' . esc_attr($maxArticlesPerStep) . '" min="1"></p>';

        // Post Status
        $statuses = ['publish', 'draft', 'pending'];
        echo '<p><label>Post Status:</label><br>';
        echo '<select name="pubmed_post_status">';
        foreach ($statuses as $st) {
            $selected = $post_status === $st ? 'selected' : '';
            echo '<option value="' . esc_attr($st) . '" ' . $selected . '>' . ucfirst($st) . '</option>';
        }
        echo '</select></p>';

        // Date Filters
        echo '<p><label>Min Date (YYYY/MM/DD):</label><br>';
        echo '<input type="text" name="pubmed_mindate" value="' . esc_attr($mindate) . '" class="regular-text"></p>';

        echo '<p><label>Max Date (YYYY/MM/DD):</label><br>';
        echo '<input type="text" name="pubmed_maxdate" value="' . esc_attr($maxdate) . '" class="regular-text"></p>';

        echo '<p><label>Date Type:</label><br>';
        echo '<select name="pubmed_datetype">';
        echo '<option value="pdat"' . selected($datetype, 'pdat', false) . '>Publication Date</option>';
        echo '<option value="edat"' . selected($datetype, 'edat', false) . '>Entry Date</option>';
        echo '</select></p>';

        // Publication Type (Checkboxes)
        echo '<p><label>Publication Type:</label><br>';
        echo '<div style="max-height: 200px; overflow:auto; border:1px solid #ccc; padding:5px;">';
        $publicationTypes = [
            'Journal Article' => 'Journal Article',
            'Review' => 'Review',
            'Systematic Review' => 'Systematic Review',
            'Clinical Trial' => 'Clinical Trial',
            'Randomized Controlled Trial' => 'Randomized Controlled Trial',
            'Meta-Analysis' => 'Meta-Analysis',
            'Case Reports' => 'Case Reports',
            'Editorial' => 'Editorial',
            'Letter' => 'Letter',
            'Comment' => 'Comment',
            'Guideline' => 'Guideline',
            'Practice Guideline' => 'Practice Guideline',
            'Comparative Study' => 'Comparative Study',
            'Evaluation Study' => 'Evaluation Study',
            'Multicenter Study' => 'Multicenter Study',
            'Observational Study' => 'Observational Study',
            'Retracted Publication' => 'Retracted Publication',
            'Technical Report' => 'Technical Report',
            'Twin Study' => 'Twin Study',
            'Validation Study' => 'Validation Study',
            'Video-Audio Media' => 'Video-Audio Media',
            'Webcast' => 'Webcast',
            'Dataset' => 'Dataset',
            'Historical Article' => 'Historical Article',
            'Interview' => 'Interview',
            'Lecture' => 'Lecture',
            'News' => 'News',
            'Patient Education Handout' => 'Patient Education Handout',
            'Portrait' => 'Portrait',
            'Retraction of Publication' => 'Retraction of Publication',
        ];
        $selectedPublicationTypes = (array) get_post_meta($post->ID, '_pubmed_pt', true); // Retrieve saved values
        foreach ($publicationTypes as $value => $label) {
            $checked = in_array($value, $selectedPublicationTypes) ? 'checked' : '';
            echo '<label style="display:block;"><input type="checkbox" name="pubmed_pt[]" value="' . esc_attr($value) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
        }
        echo '</div></p>';
        // Language
        echo '<p><label>Language:</label><br>';
        echo '<input type="text" name="pubmed_la" value="' . esc_attr($la) . '" class="regular-text">';
        echo '<p class="description">Enter language codes (e.g., "eng" for English, "fre" for French). '
            .'Separate multiple codes with commas.</p></p>';

        // Fields to Include in Content
        $fieldOptions = [
            'AuthorList' => 'Author List',
            'AbstractText' => 'Abstract',
            'Article.Journal' => 'Journal',
            'GrantList' => 'Grants',
            'PublicationType' => 'Publication Type',
            'PubMedPubDate' => 'Publication Date',
            'ReferenceList' => 'References',
        ];
        echo '<p><label>Fields to Include in Content:</label><br>';
        echo '<div style="max-height: 200px; overflow:auto; border:1px solid #ccc; padding:5px;">';
        foreach ($fieldOptions as $value => $label) {
            $checked = in_array($value, $fields) ? 'checked' : '';
            echo '<label style="display:block;"><input type="checkbox" name="pubmed_fields[]" value="' . esc_attr($value) . '" ' . $checked . '> ' . esc_html($label) . '</label>';
        }
        echo '</div></p>';

        // Fetch button
        echo '<p><input type="submit" name="fetch_pubmed" class="button button-primary" value="Fetch"></p>';

        // Display results if any
        $results = get_transient('newsapi_pubmed_fetch_results_' . $post->ID);
        if ($results) {
            echo '<div class="notice notice-success"><p>Fetched ' . intval($results['count']) . ' PubMed articles.</p>';
            if (!empty($results['post_ids'])) {
                echo '<ul>';
                foreach ($results['post_ids'] as $id) {
                    echo '<li><a href="' . get_edit_post_link($id) . '" target="_blank">' . get_the_title($id) . '</a></li>';
                }
                echo '</ul>';
            }
            echo '</div>';
            delete_transient('newsapi_pubmed_fetch_results_' . $post->ID);
        }

        // Check for fetch errors
        $error = get_transient('newsapi_pubmed_fetch_error_' . $post->ID);
        if ($error) {
            echo '<div class="notice notice-error"><p><strong>Error fetching PubMed articles:</strong> ' . esc_html($error['message']) . '</p>';
            if (!empty($error['trace'])) {
                echo '<pre>' . esc_html($error['trace']) . '</pre>';
            }
            echo '</div>';
            delete_transient('newsapi_pubmed_fetch_error_' . $post->ID);
        }
    }

    public static function saveMetaBox(int $postId): void
    {
        if (!isset($_POST['newsapi_pubmed_details_nonce_field']) || !wp_verify_nonce($_POST['newsapi_pubmed_details_nonce_field'], 'newsapi_pubmed_details_nonce')) {
            return;
        }

        // Check user capability
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save meta values
        update_post_meta($postId, '_pubmed_category', (int)($_POST['pubmed_category'] ?? 0));
        update_post_meta($postId, '_pubmed_tags', isset($_POST['pubmed_tags']) ? array_map('intval', (array)$_POST['pubmed_tags']) : []);
        update_post_meta($postId, '_pubmed_keywords', sanitize_text_field($_POST['pubmed_keywords'] ?? ''));
        update_post_meta($postId, '_pubmed_max_steps', (int)($_POST['pubmed_max_steps'] ?? 0));
        update_post_meta($postId, '_pubmed_max_articles_per_step', (int)($_POST['pubmed_max_articles_per_step'] ?? 0));
        update_post_meta($postId, '_pubmed_post_status', sanitize_text_field($_POST['pubmed_post_status'] ?? 'draft'));
        update_post_meta($postId, '_pubmed_mindate', sanitize_text_field($_POST['pubmed_mindate'] ?? ''));
        update_post_meta($postId, '_pubmed_maxdate', sanitize_text_field($_POST['pubmed_maxdate'] ?? ''));
        update_post_meta($postId, '_pubmed_datetype', sanitize_text_field($_POST['pubmed_datetype'] ?? 'pdat'));

        // Save Publication Types (as an array)
        $pubmed_pt = isset($_POST['pubmed_pt']) ? array_map('sanitize_text_field', (array)$_POST['pubmed_pt']) : [];
        update_post_meta($postId, '_pubmed_pt', $pubmed_pt);

        // Save Language
        update_post_meta($postId, '_pubmed_la', sanitize_text_field($_POST['pubmed_la'] ?? ''));

        // Save Fields to Include in Content
        $pubmed_fields = isset($_POST['pubmed_fields']) ? array_map('sanitize_text_field', (array)$_POST['pubmed_fields']) : [];
        update_post_meta($postId, '_pubmed_fields', $pubmed_fields);

        // Check if Fetch was clicked
        if (isset($_POST['fetch_pubmed'])) {
            self::fetchAndProcessPubMed($postId);
        }
    }


    public static function fetchAndProcessPubMed(int $postId): void
    {
        $debugMode = (bool) get_option('newsapi_debug', false);

        $categoryId = (int) get_post_meta($postId, '_pubmed_category', true);
        $tags = (array) get_post_meta($postId, '_pubmed_tags', true);
        $keywords = get_post_meta($postId, '_pubmed_keywords', true);
        $maxSteps = (int) get_post_meta($postId, '_pubmed_max_steps', true);
        $maxArticlesPerStep = (int) get_post_meta($postId, '_pubmed_max_articles_per_step', true);
        $postStatus = get_post_meta($postId, '_pubmed_post_status', true);
        $mindate = get_post_meta($postId, '_pubmed_mindate', true);
        $maxdate = get_post_meta($postId, '_pubmed_maxdate', true);
        $datetype = get_post_meta($postId, '_pubmed_datetype', true);
        $pt = (array) get_post_meta($postId, '_pubmed_pt', true); // Retrieve as array
        $la = get_post_meta($postId, '_pubmed_la', true);
        $fields = (array) get_post_meta($postId, '_pubmed_fields', true);

        try {
            // Convert publication types array to comma-separated string
            $ptString = implode(',', $pt);

            $articles = self::fetchPubMedArticles($keywords, $maxSteps, $maxArticlesPerStep, $mindate, $maxdate, $datetype, $ptString, $la);

            if ($debugMode) {
                set_transient('newsapi_pubmed_raw_response_' . $postId, $articles, 300);
            }

            // Publish the fetched items using WPNewsPublisherService
            $publisher = new \NewsApiPlugin\Services\WPNewsPublisherService();
            $result = $publisher->publishNews($articles, $categoryId, $tags, $postStatus, 'post');

            set_transient('newsapi_pubmed_fetch_results_' . $postId, $result, 300);
        } catch (\Exception $e) {
            $errorData = [
                'message' => $e->getMessage(),
            ];

            if ($debugMode) {
                $errorData['trace'] = $e->getTraceAsString();
                error_log("Error fetching PubMed articles: " . $e->getMessage());
                error_log("Trace: " . $e->getTraceAsString());
            }

            set_transient('newsapi_pubmed_fetch_error_' . $postId, $errorData, 300);
        }
    }

    /**
     * @throws \Exception
     */
    private static function fetchPubMedArticles(
        string $keywords,
        int $maxSteps,
        int $maxArticlesPerStep,
        string $mindate = '',
        string $maxdate = '',
        string $datetype = 'pdat',
        string $pt = '', // Comma-separated string
        string $la = ''
    ): array {
        $apiKey = get_option('newsapi_pubmed_api_key');
        if (!$apiKey) {
            throw new \Exception('PubMed API Key is not configured.');
        }

        $articles = [];
        $currentStep = 0;
        $currentKeywords = $keywords;

        while ($currentStep < $maxSteps) {
            $params = [
                'term' => $currentKeywords,
                'retmax' => $maxArticlesPerStep,
                'api_key' => $apiKey,
                'mindate' => $mindate,
                'maxdate' => $maxdate,
                'datetype' => $datetype,
                'pt' => $pt, // Comma-separated string
                'la' => $la,
            ];

            $response = wp_remote_get('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?' . http_build_query($params), [
                'timeout' => 30, // Set a higher timeout if needed
            ]);

            if (is_wp_error($response)) {
                throw new \Exception('Error fetching PubMed articles: ' . $response->get_error_message());
            }

            $body = wp_remote_retrieve_body($response);
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                throw new \Exception('Failed to parse PubMed response.');
            }

            $articleIds = [];
            foreach ($xml->IdList->Id as $id) {
                $articleIds[] = (string)$id;
            }

            foreach ($articleIds as $articleId) {
                $articleDetails = self::fetchPubMedArticleDetails($articleId);
                if ($articleDetails) {
                    $articles[] = $articleDetails;
                }
            }

            $currentStep++;
            $currentKeywords = implode(',', $articleIds); // Use the article IDs as keywords for the next step
        }

        return $articles;
    }

    private static function fetchPubMedArticleDetails(string $articleId): ?array
    {
        $apiKey = get_option('newsapi_pubmed_api_key');
        $params = [
            'db' => 'pubmed',
            'id' => $articleId,
            'api_key' => $apiKey,
        ];

        $response = wp_remote_get('https://eutils.ncbi.nlm.nih.gov/entrez/eutils/efetch.fcgi?' . http_build_query($params));
        if (is_wp_error($response)) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $xml = simplexml_load_string($body);
        if ($xml === false) {
            return null;
        }

        $title = (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleTitle;
        $abstract = (string)$xml->PubmedArticle->MedlineCitation->Article->Abstract->AbstractText;
        $url = 'https://pubmed.ncbi.nlm.nih.gov/' . $articleId;

        // Extract additional fields
        $authorList = [];
        foreach ($xml->PubmedArticle->MedlineCitation->Article->AuthorList->Author as $author) {
            $authorList[] = (string)$author->LastName . ' ' . (string)$author->ForeName;
        }

        $journal = (string)$xml->PubmedArticle->MedlineCitation->Article->Journal->Title;
        $grantList = [];
        foreach ($xml->PubmedArticle->MedlineCitation->Article->GrantList->Grant as $grant) {
            $grantList[] = (string)$grant->GrantID;
        }

        $publicationType = [];
        foreach ($xml->PubmedArticle->MedlineCitation->Article->PublicationTypeList->PublicationType as $type) {
            $publicationType[] = (string)$type;
        }

        $pubDate = (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleDate->Year . '-' .
            (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleDate->Month . '-' .
            (string)$xml->PubmedArticle->MedlineCitation->Article->ArticleDate->Day;

        $referenceList = [];
        foreach ($xml->PubmedArticle->PubmedData->ReferenceList->Reference as $reference) {
            $referenceList[] = (string)$reference->Citation;
        }

        // Prepare metadata
        $metadata = [
            'title' => $title,
            'url' => $url,
            'authors' => $authorList,
            'journal' => $journal,
            'grants' => $grantList,
            'publication_types' => $publicationType,
            'publication_date' => $pubDate,
            'references' => $referenceList,
        ];

        // Append JSON metadata
        $content = $abstract . "\n\n<pre>meta_data: " . json_encode($metadata, JSON_PRETTY_PRINT) . "</pre>";

        return [
            'title' => $title,
            'content' => $content,
            'link' => $url,
        ];
    }
}