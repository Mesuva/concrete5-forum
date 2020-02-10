<?php

namespace Concrete\Package\OrticForum\Controller\PageType;

use Concrete\Core\Page\Controller\PageTypeController;
use Concrete\Core\Routing\Redirect;
use Concrete\Package\OrticForum\Src\AuthenticationTrait;
use Core;
use User;
use Page;

class Forum extends PageTypeController
{
    use AuthenticationTrait;

    /**
     * The ErrorList instance (available after the on_start method has been called).
     *
     * @var \Concrete\Core\Error\ErrorList\ErrorList|null
     */
    private $error;

    public function __construct(Page $c)
    {
        parent::__construct($c);

        $this->error = Core::make('error');
    }

    /**
     * View the topic listing.
     */
    public function view()
    {
        $forum = Core::make('ortic/forum');
        $topicList = $forum->getTopics();

        $config = Core::make('ortic/forum/config');
        $trackViews = $config->get('ortic_forum.track_topic_views');

        $pagination = $topicList->getPagination();
        $topics = $pagination->getCurrentPageResults();

        $this->set('topics', $topics);
        $this->set('pagination', $pagination);
        $this->set('user', new User());
        $this->set('forumTopicSubject', '');
        $this->set('forumTopicMessage', '');
        $this->set('showViews', $trackViews);
        $currentPage = Page::getCurrentPage();
        $this->set('isMonitoring', $forum->isMonitoring($currentPage));

        $this->render('forum', 'ortic_forum');
    }

    /**
     * Adds a new topic to the current forum (page).
     */
    public function writeTopic()
    {
        $token = Core::make('token');
        $forum = Core::make('ortic/forum');

        if ($this->getRequest()->isPost()) {
            if (!$token->validate('writeTopic')) {
                $this->error->add($token->getErrorMessage());
            }
            if (!$this->get('subject')) {
                $this->error->add(t('You must enter a subject'));
            }
            if (!$this->get('message')) {
                $this->error->add(t('You must enter a message'));
            }

            // upload attachment, but only if there are no errors, otherwise we'd upload the file without having any
            // association to it
            $attachment = null;
            if (!$this->error->has()) {
                try {
                    $attachment = $forum->uploadAttachment($_FILES['attachment']);
                }
                catch (\Exception $ex) {
                    $this->error->add($ex->getMessage());
                }
            }

            if ($this->error->has()) {
                $this->flash('forumError', $this->error);
                $this->flash('forumTopicSubject', $this->get('subject'));
                $this->flash('forumTopicMessage', $this->get('message'));

                return Redirect::to($this->action(''));
            }
            else {
                $topicPage = $forum->writeTopic($this->post('subject'), $this->post('message'), $attachment);

                $this->flash('forumSuccess', t('Topic added'));

                return Redirect::to($topicPage->getCollectionLink());
            }
        }

        return Redirect::to($this->action(''));
    }

    /**
     * Stops the current user from receiving notifications on new answers to the current topic
     *
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    public function stopMonitoring()
    {
        $forum = Core::make('ortic/forum');
        $forum->unsubscribeFromTopicChanges(Page::getCurrentPage());

        $topicList = $forum->getTopics();
        $topics = $topicList->get();

        foreach($topics as $topic) {
            $forum->unsubscribeFromTopicChanges($topic);
        }

        $this->flash('forumSuccess', t('Monitoring disabled.'));
        return Redirect::to($this->action(''));
    }


    /**
     * Subscribers the current user to changes to the current topic
     *
     * @return \Concrete\Core\Routing\RedirectResponse
     */
    public function startMonitoring()
    {
        $forum = Core::make('ortic/forum');
        $forum->subscribeForTopicChanges(Page::getCurrentPage());

        $topicList = $forum->getTopics();
        $topics = $topicList->get();

        foreach($topics as $topic) {
           $forum->subscribeForTopicChanges($topic);
        }

        $this->flash('forumSuccess', t('Monitoring enabled.'));
        return Redirect::to($this->action(''));
    }

}
