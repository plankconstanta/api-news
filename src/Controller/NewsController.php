<?php

namespace App\Controller;

use App\Repository\NewsRepository;
use App\Repository\TagNewsRepository;
use App\Exception\NewsApiExceptionBadDataRequest;
use App\Service\NewsApiFormatServiceInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NewsController extends AbstractController
{
    const COUNT_NEWS_PER_PAGE = 5;

    private NewsRepository $newsRepository;
    private TagNewsRepository $tagNewsRepository;
    private NewsApiFormatServiceInterface $newsApiStructService;

    public function __construct(
        NewsRepository $newsRepository,
        TagNewsRepository $tagNewsRepository,
        NewsApiFormatServiceInterface $newsApiFormatService)
    {
        $this->newsRepository = $newsRepository;
        $this->tagNewsRepository = $tagNewsRepository;
        $this->newsApiFormatService = $newsApiFormatService;
    }

    /**
     * @Route("/api/news", name="api_news")
     */
    public function index(Request $request)
    {
        $page  = (int)($request->get('page') ? $request->get('page') : 1);
        $page  = $page > 0 ? $page : 1;

        $tagIds = $this->getTagIdsFromRequest($request);
        $dateFrom = $this->getDateFromRequest($request);

        $paginator = $this->newsRepository->findPaginatedByParams(self::COUNT_NEWS_PER_PAGE, $page, $dateFrom, $tagIds);

        $items = [];
        foreach ($paginator as $news) {
            /* @var News $news */
            $items[] = $this->newsApiFormatService->objectToArray($news);
        }

        $data = [
            'items' =>  [$items],
            'page'  =>  $page,
            'limit' =>  self::COUNT_NEWS_PER_PAGE,
            'total' =>  count($paginator)
        ];

        return new JsonResponse($data, Response::HTTP_OK);
    }

    private function getTagIdsFromRequest(Request $request): array
    {
        $tag = $request->get('tag');
        $tagIds = [];
        if ($tag) {
            $tag = is_array($tag) ? $tag : [$tag];
            foreach ($tag as $item) {
                if ($tagId = $this->tagNewsRepository->findByName($item)?->getId()) {
                    $tagIds[] = $tagId;
                }
            }
            // if tag is not empty but no match in db
            if (empty($tagIds)) {
                $tagIds[] = 0;
            }
        }
        return $tagIds;
    }

    private function getDateFromRequest(Request $request): ?\DateTimeImmutable
    {
        $year  = $request->get('year');
        $month = $request->get('month');

        $dateFrom = null;
        if ($year && $month) {
            try {
                $dateFrom = new \DateTimeImmutable($year . '-' . $month . '-' . '01');
            } catch (\Exception $e) {
                throw new NewsApiExceptionBadDataRequest('Illegal date');
            }

        }
        return $dateFrom;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedServices(): array
    {
        return [];
    }
}