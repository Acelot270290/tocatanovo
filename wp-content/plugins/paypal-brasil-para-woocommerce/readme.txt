=== PayPal Brasil para WooCommerce ===

Contributors: [apuhlmann](https://profiles.wordpress.org/apuhlmann)
Tags: paypal, paypal plus, woocommerce, woo commerce, checkout transparente, transparente, pagamento, gateway, paypal brasil, ecommerce, e-commerce
Requires at least: 4.4
Tested up to: 5.6
Stable tag: 1.3
Requires PHP: 5.6
License: GPLv2 or later
License URI:  [http://www.gnu.org/licenses/gpl-2.0.html](http://www.gnu.org/licenses/gpl-2.0.html)
WC requires at least: 3.6
WC tested up to:  4.7

Adicione facilmente opções de pagamento do PayPal à sua loja do WooCommerce.

== Description ==

= O Checkout Transparente do PayPal é agora PayPal Brasil para WooCommerce! =

Um pacote completo de soluções de pagamento para a sua loja. Além do Checkout Transparente, você agora pode oferecer a tradicional carteira digital do PayPal, possibilitando aos seus clientes pagar utilizando tanto um cartão de crédito quanto sua conta PayPal.

= Soluções =


* **Checkout Transparente\*:** o cliente efetua o pagamento diretamente em seu site, sem a necessidade de ter uma conta PayPal, utilizando apenas os dados de seu cartão de crédito;
* **Carteira Digital:** a solução tradicional do PayPal onde os clientes utilizam suas contas PayPal - ou criam uma no momento da compra - em ambiente seguro e sem redirecionamento, mantendo a experiência do checkout dentro da sua loja;
* **PayPal no Carrinho:** a carteira digital PayPal disponível diretamente no carrinho de sua loja. O cliente pula algumas etapas do processo e efetua a compra diretamente do carrinho, oferecendo uma experiência mais ágil e segura;
* **Salvar carteira digital\*:** ganhe agilidade salvando a carteira digital PayPal de seu cliente em seu cadastro, assim, em sua próxima compra ele não precisará mais se logar em sua conta PayPal para aprovar o pedido.

*\* Esta funcionalidade requer aprovação do PayPal, entre em contato pelo 0800 721 6959 e solicite agora mesmo.*

= Vantagens do PayPal =

* **Segurança:** nível máximo de certificação de segurança PCI Compliance e criptografia em todas as transações;
* **Programa de Proteção ao Vendedor\*:** protege suas vendas em casos de “chargebacks”, reclamações ou cancelamentos solicitados pelo comprador;
* **Facilidade no recebimento das vendas:** parcele suas vendas em até 12 vezes e receba em 24 horas**, sem tarifa incremental de antecipação;
* **Atendimento especializado:** atendimento comercial e técnico para tirar suas dúvidas e te ajudar com integrações. Seu cliente também conta com um atendimento bilíngue 24x7;
* **Venda para novos clientes no exterior:** receba pagamentos de compradores de mais de 200 mercados*** diferentes e para 250 milhões de compradores ao redor do mundo.

*\* Sujeito ao cumprimento dos requisitos do Programa de Proteção ao Vendedor e Comprador.*
*\*\* Pagamentos recebidos na conta do PayPal e sujeitos a análise de risco e crédito pelo PayPal.*
*\*\*\* O Checkout Transparente do PayPal só permite recebimento nas moedas Real Brasileiro (BRL) e Dólar Americano (USD).*

= Para quem este módulo está disponível? =

O Checkout Transparente está disponível apenas para contas PayPal cadastradas com CNPJ (Conta Empresa). Caso a sua conta seja de pessoa física, você deve abrir uma conta PayPal de pessoa jurídica por [este link](https://www.paypal.com/bizsignup/).

Já a Carteira Digital, você pode utilizar tanto com uma conta empresa quanto uma conta pessoa física.

= Aprovações =

Algumas das soluções PayPal requerem aprovação comercial para serem utilizadas:

* **Checkout Transparente & Salvar carteira digital:** entre em contato pelo 0800 721 6959 e solicite agora mesmo.

= Requisitos =

Por padrão o WooCommerce não pede no cadastro do cliente as informações de CPF/CNPJ. Entretanto, estas informações são necessárias para que as soluções do PayPal possam desempenhar uma análise de risco mais apurada. Assim, este campo torna-se obrigatório para o uso deste plugin.

Recomendamos a utilização de um plugin, por exemplo o “[Brazilian Market on WooCommerce](https://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/)".

= Compatibilidade =

Compatível à partir da versão 3.6 do WooCommerce.

= Instalação =

Instale o módulo normalmente pelo gerenciador de módulos ou à partir do download deste repositório copie o conteúdo dentro da pasta de plugins da sua instalação do WooCommerce.

Caso tenha alguma dúvida entre em contato conosco pelo 0800 047 4482.

= Dúvidas/Suporte =

Caso tenha alguma dúvida ou dificuldade na utilização do plugin acesse a seção de Suporte por este [link](https://wordpress.org/support/plugin/paypal-brasil-para-woocommerce).

== Frequently Asked Questions ==

= Instalação do plugin: =

* Envie os arquivos do plugin para a pasta "wp-content/plugins", ou instale usando o instalador de plugins do WordPress.
* Ative o plugin.

== Changelog ==

= 1.0 =
* Lançamento do plugin.

= 1.0.1 =
* Alterado cálculos com preços em centavos para funções de precisão do PHP.

= 1.0.2 =
* Criado fallback para casos que não possua a extensão BC math ativada.

= 1.0.3 =
* Otimizado método para cálculos matemáticos.
* Melhoria nos tratamentos dos webhooks.
* Ajuste para alguns plugins de descontos.
* Corrigido exibição do shortcut mesmo com o gateway desabilitado.
* Corrigido problema com reembolso.
* Corrigido problema com webhooks.
* Corrigido problema com produtos digitais utilizando o PayPal no Carrinho.

= 1.0.4 =
* Adicionado suporte a alguns plugins de desconto.
* Corrigido problema que causava carregamento infinito devido a plugins de desconto.
* Aprimorado tratamentos para plugins de desconto e desconto nativo.

= 1.0.5 =
* Modificado tratamentos das ações no Checkout Transparente.
* Corrigido possível problema de webhooks para algumas instalações.
* Atualizações de seguranças para pacotes de dependências.
* Corrigido página de pagamento para pedido manual.
* Removido scripts quando o método de pagamento não está ativado.
* Corrigido problema com produtos virtuais.

= 1.0.6 =
* Corrigido alguns problemas com webhooks.
* Corrigido conflito com o plugin Checkout Transparente do PayPal
* Corrigido problema de estilização na Carteira Digital.

= 1.1.0 =
* Adicionado validação nos valores do pedido do Checkout Transparente.

= 1.1.1 =
* Corrigido erros no checkout.
* Corrigido bug que duplicava botão no checkout.
* Adicionado mensagem de autorização para recursos do Checkout Transparente.
* Alterado telefone de suporte.

= 1.1.2 =
* Removido segundo botão abaixo do "Pague com PayPal".

= 1.1.3 =
* Corrigido warnings.
* Atualizado versão de suporte.

= 1.1.4 =
* Corrigido problema de fechar a janela do PayPal.

= 1.2.0 =
* Ajustes das chamadas para API do PayPal.
* Corrigido formatação de endereço.

= 1.2.1 =
* Otimizado validação de estados.
* Melhorado suporte para múltiplas moedas.

= 1.3.0 =
* Adicionado suporte para países sem estados.

== Upgrade Notice ==

= 1.3.0 =
* Adicionado suporte para países sem estados.

== Screenshots ==

1. Exemplo de dados não preenchidos no tema Storefront.
2. Exemplo de checkout com cartão de crédito salvo no tema Storefront.
3. Área de pagamento com PayPal.
4. Pagamento com a Carteira Digital do PayPal.
5. Tela de login na conta do PayPal.
