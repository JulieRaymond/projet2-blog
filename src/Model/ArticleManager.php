<?php

namespace App\Model;

use PDO;

class ArticleManager extends AbstractManager
{
    public const TABLE = 'article';

    public function addArticle(array $data)
    {
        $query = 'INSERT INTO ' .
            static::TABLE .
            ' (title, content, image, blog_user_id) 
        VALUES (:title, :content, :image, :blog_user_id)';
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':title', $data['title']);
        $statement->bindValue(':content', $data['content']);
        $statement->bindValue(':image', $data['image']);
        $statement->bindValue(':blog_user_id', $data['blog_user_id'], \PDO::PARAM_INT);
        $statement->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function editArticle(int $articleId, array $data)
    {
        $query = 'UPDATE ' . static::TABLE . ' SET title = :title, content = :content';

        // Vérifie si la clé 'image' existe avant de l'ajouter à la requête
        if (isset($data['image'])) {
            $query .= ', image = :image';
        }

        $query .= ' WHERE id = :id';

        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':title', $data['title']);
        $statement->bindValue(':content', $data['content']);

        // Vérifie à nouveau avant de lier la valeur de 'image'
        if (isset($data['image'])) {
            $statement->bindValue(':image', $data['image']);
        }

        $statement->bindValue(':id', $articleId, \PDO::PARAM_INT);
        $statement->execute();
    }


    public function deleteArticle(int $articleId)
    {
        $query = 'DELETE FROM ' . static::TABLE . ' WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':id', $articleId, \PDO::PARAM_INT);
        $statement->execute();
    }

    public function getArticlesByUserId(int $userId)
    {
        $query = "SELECT A.*, BU.name AS author_name, COUNT(C.id) AS comment_count,
                GROUP_CONCAT(CAT.name SEPARATOR ', ') AS categories
                FROM article A
                INNER JOIN blog_user BU ON A.blog_user_id = BU.id
                LEFT JOIN commentary C ON A.id = C.article_id
                LEFT JOIN article_category AC ON A.id = AC.article_id
                LEFT JOIN category CAT ON AC.category_id = CAT.id
                WHERE A.blog_user_id = :user_id
                GROUP BY A.id
                ORDER BY A.date DESC";
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }


    public function getAllArticles()
    {
        $query = "SELECT A.*, BU.name AS author_name, COUNT(C.id) AS comment_count, 
                GROUP_CONCAT(CAT.name SEPARATOR ', ') AS categories
                FROM article A
                INNER JOIN blog_user BU ON A.blog_user_id = BU.id
                LEFT JOIN commentary C ON A.id = C.article_id
                LEFT JOIN article_category AC ON A.id = AC.article_id
                LEFT JOIN category CAT ON AC.category_id = CAT.id
                GROUP BY A.id, BU.name
                ORDER BY A.date DESC";

        return $this->pdo->query($query)->fetchAll();
    }

    public function getArticleById(int $articleId)
    {
        $query = "SELECT A.*, BU.name AS author_name
                  FROM " . static::TABLE . " AS A 
                  LEFT JOIN blog_user BU ON A.blog_user_id = BU.id 
                  WHERE A.id = :id";

        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':id', $articleId, \PDO::PARAM_INT);
        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result;
    }

    public function getAllArticlesWithComments()
    {
        // D'abord, récupérez tous les articles
        $articles = $this->getAllArticles();

        // Ensuite, pour chaque article, récupérez les commentaires
        foreach ($articles as $key => $article) {
            $articleId = $article['id'];
            $commentQuery = "SELECT * FROM commentary WHERE article_id = :articleId";
            $statement = $this->pdo->prepare($commentQuery);
            $statement->bindValue(':articleId', $articleId, \PDO::PARAM_INT);
            $statement->execute();
            $comments = $statement->fetchAll();

            // Ajoutez les commentaires au tableau de l'article
            $articles[$key]['comments'] = $comments;
        }

        return $articles;
    }

    public function getArticlesWithCategoriesByUserId($userId)
    {
        $query = "SELECT A.*, GROUP_CONCAT(CAT.name SEPARATOR ', ') AS categories
                  FROM article A
                  LEFT JOIN article_category AC ON A.id = AC.article_id
                  LEFT JOIN category CAT ON AC.category_id = CAT.id
                  WHERE A.blog_user_id = :userId
                  GROUP BY A.id
                  ORDER BY A.date DESC";

        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':userId', $userId, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getArticlesByCategory($categoryId = null)
    {
        $query = "SELECT A.*, BU.name AS author_name
                  FROM article A
                  INNER JOIN blog_user BU ON A.blog_user_id = BU.id";

        if ($categoryId) {
            $query .= " JOIN article_category AC ON A.id = AC.article_id
                        WHERE AC.category_id = :categoryId";
        }

        $query .= " GROUP BY A.id, BU.name";

        $statement = $this->pdo->prepare($query);

        if ($categoryId) {
            $statement->bindValue(':categoryId', $categoryId, PDO::PARAM_INT);
        }

        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getArticlesByCategoryName($searchTerm)
    {
        $query = "SELECT A.*, BU.name AS author_name
                  FROM article A
                  INNER JOIN blog_user BU ON A.blog_user_id = BU.id
                  INNER JOIN article_category AC ON A.id = AC.article_id
                  INNER JOIN category C ON AC.category_id = C.id
                  WHERE C.name LIKE :searchTerm
                  GROUP BY A.id
                  ORDER BY A.date DESC";
        $statement = $this->pdo->prepare($query);
        $statement->bindValue(':searchTerm', '%' . $searchTerm . '%', PDO::PARAM_STR);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
