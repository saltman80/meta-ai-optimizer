private function __construct() {
        $this->init();
    }

    public static function getInstance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __clone() {}
    public function __wakeup() {
        throw new Exception( 'Cannot unserialize singleton' );
    }

    private function init() {
        add_action( 'admin_menu', array( $this, 'registerMenus' ) );
        add_action( 'admin_init', array( $this, 'registerSettings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
    }

    public function registerMenus() {
        add_menu_page(
            'Meta AI Optimizer',
            'Meta AI Optimizer',
            'manage_options',
            'mao_dashboard',
            array( $this, 'renderDashboardPage' ),
            'dashicons-admin-generic',
            75
        );
        add_submenu_page(
            'mao_dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'mao_settings',
            array( $this, 'renderSettingsPage' )
        );
    }

    public function enqueueAssets( $hook ) {
        if ( 'toplevel_page_mao_dashboard' === $hook ) {
            wp_enqueue_script(
                'mao-dashboard-js',
                plugin_dir_url( __FILE__ ) . 'assets/js/mao-dashboard.js',
                array( 'wp-element', 'wp-i18n', 'wp-api-fetch' ),
                '1.0.0',
                true
            );
            wp_enqueue_style(
                'mao-admin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/mao-admin.css',
                array(),
                '1.0.0'
            );
            wp_localize_script( 'mao-dashboard-js', 'maoSettings', array(
                'apiUrl' => esc_url_raw( rest_url( 'meta-ai-optimizer/v1/' ) ),
                'nonce'  => wp_create_nonce( 'wp_rest' ),
            ) );
        }
        if ( 'mao_dashboard_page_mao_settings' === $hook ) {
            wp_enqueue_style(
                'mao-admin-css',
                plugin_dir_url( __FILE__ ) . 'assets/css/mao-admin.css',
                array(),
                '1.0.0'
            );
        }
    }

    public function renderDashboardPage() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'meta-ai-optimizer' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Meta AI Optimizer', 'meta-ai-optimizer' ); ?></h1>
            <div id="mao-dashboard-app"></div>
        </div>
        <?php
    }

    public function renderSettingsPage() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'meta-ai-optimizer' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Meta AI Optimizer Settings', 'meta-ai-optimizer' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'meta_ai_optimizer_settings_group' );
                do_settings_sections( 'mao_settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function registerSettings() {
        register_setting(
            'meta_ai_optimizer_settings_group',
            'meta_ai_optimizer_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );
        register_setting(
            'meta_ai_optimizer_settings_group',
            'meta_ai_optimizer_api_endpoint',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );
        register_setting(
            'meta_ai_optimizer_settings_group',
            'meta_ai_optimizer_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        add_settings_section(
            'mao_api_section',
            __( 'API Configuration', 'meta-ai-optimizer' ),
            array( $this, 'renderApiSectionDescription' ),
            'mao_settings'
        );

        add_settings_field(
            'meta_ai_optimizer_api_key',
            __( 'API Key', 'meta-ai-optimizer' ),
            array( $this, 'renderApiKeyField' ),
            'mao_settings',
            'mao_api_section'
        );

        add_settings_field(
            'meta_ai_optimizer_api_endpoint',
            __( 'API Endpoint', 'meta-ai-optimizer' ),
            array( $this, 'renderApiEndpointField' ),
            'mao_settings',
            'mao_api_section'
        );

        add_settings_field(
            'meta_ai_optimizer_model',
            __( 'Model', 'meta-ai-optimizer' ),
            array( $this, 'renderModelField' ),
            'mao_settings',
            'mao_api_section'
        );
    }

    public function renderApiSectionDescription() {
        echo '<p>' . esc_html__( 'Enter your OpenAI or OpenRouter API credentials below:', 'meta-ai-optimizer' ) . '</p>';
    }

    public function renderApiKeyField() {
        $value = esc_attr( get_option( 'meta_ai_optimizer_api_key', '' ) );
        printf(
            '<input type="text" id="meta_ai_optimizer_api_key" name="meta_ai_optimizer_api_key" value="%s" class="regular-text" />',
            $value
        );
    }

    public function renderApiEndpointField() {
        $value = esc_url( get_option( 'meta_ai_optimizer_api_endpoint', '' ) );
        printf(
            '<input type="url" id="meta_ai_optimizer_api_endpoint" name="meta_ai_optimizer_api_endpoint" value="%s" class="regular-text" />',
            $value
        );
    }

    public function renderModelField() {
        $value = esc_attr( get_option( 'meta_ai_optimizer_model', '' ) );
        printf(
            '<input type="text" id="meta_ai_optimizer_model" name="meta_ai_optimizer_model" value="%s" class="regular-text" />',
            $value
        );
    }
}

OptimizerMenuManager::getInstance();