<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\controllers;

use Craft;
use craft\base\Element;
use craft\commerce\elements\Product;
use craft\commerce\helpers\Product as ProductHelper;
use craft\commerce\Plugin;
use craft\errors\ElementNotFoundException;
use craft\errors\MissingComponentException;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\Response;
use yii\web\ServerErrorHttpException;

/**
 * Class Products Preview Controller
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class ProductsPreviewController extends Controller
{
    /**
     * @inheritdoc
     */
    protected $allowAnonymous = true;

    /**
     * Previews a product.
     *
     * @throws HttpException
     */
    public function actionPreviewProduct(): Response
    {
        $this->requirePostRequest();

        $product = ProductHelper::populateProductFromPost();

        $this->enforceEditProductPermissions($product);

        return $this->_showProduct($product);
    }

    /**
     * Redirects the client to a URL for viewing a disabled product on the front end.
     *
     * @param mixed $productId
     * @param mixed $siteId
     * @return Response
     * @throws Exception
     * @throws HttpException
     * @throws InvalidConfigException
     */
    public function actionShareProduct($productId, $siteId): Response
    {
        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $siteId);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->enforceEditProductPermissions($product);

        // Make sure the product actually can be viewed
        if (!Plugin::getInstance()->getProductTypes()->isProductTypeTemplateValid($product->getType(), $product->siteId)) {
            throw new HttpException(404);
        }

        // Create the token and redirect to the product URL with the token in place
        $token = Craft::$app->getTokens()->createToken([
            'commerce/products-preview/view-shared-product', ['productId' => $product->id, 'siteId' => $siteId],
        ]);

        $url = UrlHelper::urlWithToken($product->getUrl(), $token);

        return $this->redirect($url);
    }

    /**
     * Shows a product/draft/version based on a token.
     *
     * @param mixed $productId
     * @param mixed $site
     * @throws HttpException
     */
    public function actionViewSharedProduct($productId, $site = null): ?Response
    {
        $this->requireToken();

        $product = Plugin::getInstance()->getProducts()->getProductById($productId, $site);

        if (!$product) {
            throw new HttpException(404);
        }

        $this->_showProduct($product);

        return null;
    }

    /**
     * Save a new or existing product.
     *
     * @throws Exception
     * @throws HttpException
     * @throws Throwable
     * @throws ElementNotFoundException
     * @throws MissingComponentException
     * @throws BadRequestHttpException
     * @deprecated in 3.4.8. Use [[\craft\commerce\controllers\ProductsController::actionSaveProduct()]] instead.
     * @todo Remove in 4.0
     */
    public function actionSaveProduct(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();

        $product = ProductHelper::populateProductFromPost();

        $this->enforceEditProductPermissions($product);

        // Save the entry (finally!)
        if ($product->enabled && $product->enabledForSite) {
            $product->setScenario(Element::SCENARIO_LIVE);
        }

        if (!Craft::$app->getElements()->saveElement($product)) {
            return $this->asModelFailure(
                $product,
                Craft::t('commerce', 'Couldn’t save product.'),
                'product'
            );
        }

        return $this->asModelSuccess(
            $product,
            Craft::t('commerce', 'Couldn’t save product.'),
            'product',
            [
                'id' => $product->id,
                'title' => $product->title,
                'status' => $product->getStatus(),
                'url' => $product->getUrl(),
                'cpEditUrl' => $product->getCpEditUrl(),
            ]
        );
    }

    /**
     * @throws ForbiddenHttpException
     * @since 3.4.8
     */
    protected function enforceEditProductPermissions(Product $product): void
    {
        if (!$product->getIsEditable()) {
            throw new ForbiddenHttpException('User is not permitted to edit this product');
        }
    }

    /**
     * @throws ForbiddenHttpException
     * @deprecated in 3.4.8. Use [[enforceEditProductPermissions()]] instead.
     */
    protected function enforceProductPermissions(Product $product): void
    {
        $this->enforceEditProductPermissions($product);
    }

    /**
     * Displays a product.
     *
     * @throws InvalidConfigException
     * @throws ServerErrorHttpException
     */
    private function _showProduct(Product $product): Response
    {
        $productType = $product->getType();

        if (!$productType) {
            throw new ServerErrorHttpException('Product type not found.');
        }

        $siteSettings = $productType->getSiteSettings();

        if (!isset($siteSettings[$product->siteId]) || !$siteSettings[$product->siteId]->hasUrls) {
            throw new ServerErrorHttpException('The product ' . $product->id . ' doesn\'t have a URL for the site ' . $product->siteId . '.');
        }

        $site = Craft::$app->getSites()->getSiteById($product->siteId);

        if (!$site) {
            throw new ServerErrorHttpException('Invalid site ID: ' . $product->siteId);
        }

        Craft::$app->language = $site->language;

        // Have this product override any freshly queried products with the same ID/site
        if ($product->id) {
            Craft::$app->getElements()->setPlaceholderElement($product);
        }

        $this->getView()->getTwig()->disableStrictVariables();

        return $this->renderTemplate($siteSettings[$product->siteId]->template, [
            'product' => $product,
        ]);
    }
}
