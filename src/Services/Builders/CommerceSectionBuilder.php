<?php
namespace ScriptDevelop\WhatsappManager\Services\Builders;

class CommerceSectionBuilder
{
    private array $sections = [];

    /**
     * Añade una sección de productos al catálogo interactivo (MPM).
     *
     * @param string $title El título de la sección (Max 24 caracteres sin markdown).
     * @param array $skus Un array con los product_retailer_ids (SKUs) del catálogo comercial. Max 30 por mensaje en total.
     * @return self
     */
    public function addSection(string $title, array $skus): self
    {
        $productItems = array_map(function ($sku) {
            return ['product_retailer_id' => $sku];
        }, $skus);

        $this->sections[] = [
            'title' => $title,
            'product_items' => $productItems
        ];

        return $this;
    }

    /**
     * Obtiene el array de secciones estructurado para Meta.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->sections;
    }
}
