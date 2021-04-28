<div class="admin-options-container">

    <div class="alert-dialog" v-if="showAlert && mode === 'live'">
        <div class="dialog-content">
            <img class="error-image"
                 src="<?php echo esc_url( plugins_url( 'assets/images/triangle.png', PAYPAL_PAYMENTS_MAIN_FILE ) ); ?>">
            <p>Prezado cliente, o <strong>Checkout Transparente do PayPal</strong> só funciona em produção mediante
                liberação comercial, caso você já tenha recebido a aprovação, ignore esta mensagem. Caso o contrário,
                ligue na nossa central de vendas (0800 721 6959) e solicite agora mesmo.</p>
            <p>Se você está visualizando o sinal de "proibido" durante o checkout, a sua conta não está liberada para
                utilização.</p>
            <div class="dialog-actions">
                <button class="close-button" type="button" v-on:click="closeAlert">Ok</button>
            </div>
        </div>
    </div>

	<?php if ( ( empty( $_POST ) && $this->enabled === 'yes' ) || ( isset( $_POST ) && $this->get_updated_values()['enabled'] === 'yes' ) ): ?>

        <!-- CREDENTIALS ERROR -->
		<?php if ( get_option( $this->get_option_key() . '_validator' ) === 'no' ): ?>
            <div id="message" class="error inline">
                <p>
                    <strong><?php _e( 'Suas credenciais não são válidas. Por favor, verifique os dados informados.',
							'paypal-brasil-para-woocommerce' ); ?></strong>
                </p>
            </div>
		<?php elseif ( ( ! empty( $_POST ) && $this->get_updated_values()['reference_enabled'] === 'yes' && get_option( $this->get_option_key() . '_reference_transaction_validator' ) === 'no' )
		               || ( empty( $_POST ) && $this->reference_enabled === 'yes' && get_option( $this->get_option_key() . '_reference_transaction_validator' ) === 'no' ) ): ?>
            <div id="message" class="error inline">
                <p>
                    <strong><?php _e( 'Não foi possível ativar a funcionalidade "Salvar Carteira Digital" pois verificamos que a sua conta PayPal não tem permissão para utilizar este produto. Entre em contato pelo 0800 721 6959 do PayPal e solicite a sua liberação.',
							'paypal-brasil-para-woocommerce' ); ?></strong>
                </p>
            </div>
		<?php endif; ?>

        <!-- WEBHOOK -->
		<?php if ( ! $this->get_webhook_id() ): ?>
            <div id="paypal-brasil-message-webhook" class="error inline">
                <p>
                    <strong><?php _e( 'Não foi possível criar as configurações de webhook. Tente salvar novamente.',
							'paypal-brasil-para-woocommerce' ); ?></strong>
                </p>
            </div>
		<?php endif; ?>

	<?php endif; ?>

    <img class="banner"
         srcset="<?php echo esc_attr( plugins_url( 'assets/images/banner-plus-2x.png',
		     PAYPAL_PAYMENTS_MAIN_FILE ) ); ?> 2x"
         src="<?php echo esc_attr( plugins_url( 'assets/images/banner-plus.png', PAYPAL_PAYMENTS_MAIN_FILE ) ); ?>"
         title="<?php _e( 'PayPal Brasil', 'paypal-brasil-para-woocommerce' ); ?>"
         alt="<?php _e( 'PayPal Brasil', 'paypal-brasil-para-woocommerce' ); ?>">

	<?php echo wp_kses_post( wpautop( $this->get_method_description() ) ); ?>

    <table class="form-table">

        <tbody>

        <!-- HABILITAR -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>">Habilitar/Desabilitar</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Habilitar/Desabilitar</span></legend>
                    <label for="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>">
                        <input type="checkbox"
                               class="test"
                               name="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>"
                               id="<?php echo esc_attr( $this->get_field_key( 'enabled' ) ); ?>"
                               value="<?php echo esc_attr( $this->enabled ); ?>"
                               v-model="enabled"
                               true-value="yes"
                               false-value="">
                        Habilitar</label><br>
                </fieldset>
            </td>
        </tr>

        <!-- NOME DE EXIBIÇÃO -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $this->get_field_key( 'title_complement' ) ); ?>"><?php echo esc_html( $this->get_form_fields()['title_complement']['title'] ); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php echo esc_html( $this->get_form_fields()['title_complement']['title'] ); ?></span>
                    </legend>
                    <input class="input-text regular-input"
                           type="text"
                           name="<?php echo esc_attr( $this->get_field_key( 'title_complement' ) ); ?>"
                           id="<?php echo esc_attr( $this->get_field_key( 'title_complement' ) ); ?>"
                           v-model="titleComplement"
                           placeholder="Exemplo: Parcelado em até 12x">
                    <p class="description">Será exibido no checkout: Cartão de Crédito {{titleComplement ? '(' +
                        titleComplement +
                        ')':
                        ''}}</p>
                </fieldset>
            </td>
        </tr>

        <!-- MODO -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label
                        for="<?php echo esc_attr( $this->get_field_key( 'mode' ) ); ?>"><?php echo esc_html( $this->get_form_fields()['mode']['title'] ); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text">
                        <span><?php echo esc_html( $this->get_form_fields()['mode']['title'] ); ?></span>
                    </legend>
                    <select class="select"
                            id="<?php echo esc_attr( $this->get_field_key( 'mode' ) ); ?>"
                            name="<?php echo esc_attr( $this->get_field_key( 'mode' ) ); ?>"
                            v-model="mode">
                        <option value="live">Produção</option>
                        <option value="sandbox" selected="selected">Sandbox</option>
                    </select>
                    <p class="description">Utilize esta opção para alternar entre os modos Sandbox e Produção. Sandbox é
                        utilizado para testes e Produção para compras reais.</p>
                </fieldset>
            </td>
        </tr>

        <!-- CLIENT ID LIVE -->

        <tr valign="top" :class="{hidden: !isLive()}">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'client_live' ) ); ?>">Client ID
                    (produção)</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Client ID</span></legend>
                    <input class="input-text regular-input"
                           type="text"
                           id="<?php echo esc_attr( $this->get_field_key( 'client_live' ) ); ?>"
                           name="<?php echo esc_attr( $this->get_field_key( 'client_live' ) ); ?>"
                           v-model="client.live">
                    <p class="description">Para gerar o Client ID acesse <a
                                href="https://developer.paypal.com/docs/classic/lifecycle/sb_credentials/"
                                target="_blank">aqui</a>
                        e procure pela seção “REST API apps”.</p>
                </fieldset>
            </td>
        </tr>

        <!-- CLIENT ID SANDBOX -->

        <tr valign="top" :class="{hidden: isLive()}">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'client_sandbox' ) ); ?>">Client ID
                    (sandbox) </label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Client ID</span></legend>
                    <input class="input-text regular-input"
                           type="text"

                           id="<?php echo esc_attr( $this->get_field_key( 'client_sandbox' ) ); ?>"
                           name="<?php echo esc_attr( $this->get_field_key( 'client_sandbox' ) ); ?>"
                           v-model="client.sandbox">
                    <p class="description">Para gerar o Client ID acesse <a
                                href="https://developer.paypal.com/docs/classic/lifecycle/sb_credentials/"
                                target="_blank">aqui</a>
                        e procure pela seção “REST API apps”.</p>
                </fieldset>
            </td>
        </tr>

        <!-- SECRET LIVE -->

        <tr valign="top" :class="{hidden: !isLive()}">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'secret_live' ) ); ?>">Secret (produção)</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Secret</span></legend>
                    <input class="input-text regular-input"
                           type="text"
                           id="<?php echo esc_attr( $this->get_field_key( 'secret_live' ) ); ?>"
                           name="<?php echo esc_attr( $this->get_field_key( 'secret_live' ) ); ?>"
                           v-model="secret.live">
                    <p class="description">Para gerar o Secret acesse <a
                                href="https://developer.paypal.com/docs/classic/lifecycle/sb_credentials/"
                                target="_blank">aqui</a>
                        e procure pela seção “REST API apps”.</p>
                </fieldset>
            </td>
        </tr>

        <!-- SECRET SANDBOX -->

        <tr valign="top" :class="{hidden: isLive()}">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'secret_sandbox' ) ); ?>">Secret
                    (sandbox)</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Secret (sandbox)</span></legend>
                    <input class="input-text regular-input"
                           type="text"
                           id="<?php echo esc_attr( $this->get_field_key( 'secret_sandbox' ) ); ?>"
                           name="<?php echo esc_attr( $this->get_field_key( 'secret_sandbox' ) ); ?>"

                           v-model="secret.sandbox">
                    <p class="description">Para gerar o Secret acesse <a
                                href="https://developer.paypal.com/docs/classic/lifecycle/sb_credentials/"
                                target="_blank">aqui</a>
                        e procure pela seção “REST API apps”.</p>
                </fieldset>
            </td>
        </tr>

        <h2>Configurações Avançadas</h2>

        <!-- FORM HEIGHT -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>">Altura do
                    formulário</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Altura do formulário</span></legend>
                    <input class="input-text regular-input"
                           type="range"
                           min="400"
                           max="700"
                           id="<?php echo esc_attr( $this->get_field_key( 'form_height' ) ); ?>"
                           name="<?php echo esc_attr( $this->get_field_key( 'form_height' ) ); ?>"
                           v-model="formHeight">
                    <span class="form-height-value">{{formHeight}}px</span>
                    <p class="description">Utilize esta opção para definir uma altura máxima do formulário de cartão de
                        crédito (será considerado um valor em pixels). Será aceito um valor em pixels entre 400 -
                        550.</p>
                </fieldset>
            </td>
        </tr>

        <!-- PREFIXO -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>">Prefixo no número do
                    pedido</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Prefixo no número do pedido</span></legend>
                    <input class="input-text regular-input"
                           type="text"
                           id="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>"
                           name="<?php echo esc_attr( $this->get_field_key( 'invoice_id_prefix' ) ); ?>"
                           v-model="invoiceIdPrefix">
                    <p class="description">Adicione um prefixo no número do pedido, isto é útil para a sua identificação
                        quando você possui mais de uma loja processando pelo PayPal.</p>
                </fieldset>
            </td>
        </tr>

        <!-- MODO DEPURAÇÃO -->

        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>">Modo depuração</label>
            </th>
            <td class="forminp">
                <fieldset>
                    <legend class="screen-reader-text"><span>Modo depuração</span></legend>
                    <label for="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>">
                        <input type="checkbox"
                               id="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>"
                               name="<?php echo esc_attr( $this->get_field_key( 'debug' ) ); ?>"
                               v-model="debugMode"
                               true-value="yes"
                               false-value="">
                        Habilitar</label><br>
                    <p class="description">Os logs serão salvos no caminho: <a target="_blank"
                                                                               href="<?php echo esc_url( admin_url( sprintf( 'admin.php?page=wc-status&tab=logs&log_file=%s',
						                                                           paypal_brasil_get_log_file( $this->id ) ) ) ); ?>">Status
                            do Sistema &gt; Logs</a>.</p>
                </fieldset>
            </td>
        </tr>

        </tbody>

    </table>

</div>
