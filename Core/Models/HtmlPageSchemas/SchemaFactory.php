<?php
namespace Core\Models\HtmlPageSchemas;
use Core\Models\Layout\BaseLayout;
class SchemaFactory {
    protected BaseLayout $layout;

    public function __construct(BaseLayout $layout) {
        $this->layout = $layout;
    }

    public function create(string $strSchemaFQCN): BaseHtmlPageSchema {
        return new $strSchemaFQCN($this->layout);
    }
}