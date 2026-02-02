<?php
$baseSelect = "SELECT 
    r.*, 
    p.product_name,
    GROUP_CONCAT(DISTINCT t.tag_name) AS tag_names,
    GROUP_CONCAT(DISTINCT ri.ingredient_id) AS ingredient_ids
FROM recipes r
LEFT JOIN products p ON r.linked_product_id = p.product_id
LEFT JOIN recipe_tag rt ON r.recipe_id = rt.recipe_id
LEFT JOIN tags t ON rt.tag_id = t.tag_id
LEFT JOIN recipe_ingredients ri ON r.recipe_id = ri.recipe_id";
?>