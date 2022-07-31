<?php

namespace Limas\Entity;


interface CategoryPathInterface
{
	public function setCategoryPath(string $categoryPath);
	public function generateCategoryPath(string $pathSeparator): string;
}
