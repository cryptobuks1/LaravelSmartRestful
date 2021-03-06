<?php

namespace Alive2212\LaravelSmartRestful;

use Alive2212\LaravelOnionPattern\BasePattern;
use Alive2212\LaravelQueryHelper\QueryHelper;
use Alive2212\LaravelRequestHelper\RequestHelper;
use Alive2212\LaravelSmartResponse\ResponseModel;
use Alive2212\LaravelSmartResponse\SmartResponse;
use Alive2212\LaravelStringHelper\StringHelper;
use App\Group;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;


abstract class BaseController extends Controller
{
    /**
     * to use this class
     * create message list as messages in message file
     * override __constructor and define your model
     * define your rules for index,store and update
     */

    /**
     * base pattern
     */
    use BasePattern;

    /**
     * store tag to store data of request
     *
     * @var string
     */
    protected $cachedTag = 'response_cached';

    /**
     * store tag to store data of request
     *
     * @var string
     */
    protected $storeTag = 'response_store';

    /**
     * function name tag for pass data to response
     *
     * @var string
     */
    protected $functionNameResponseTag = 'response_function';

    /**
     * data tag for pass data to response
     *
     * @var string
     */
    protected $dataResponseTag = 'response_data';

    /**
     * @var string
     */
    protected $shortTagName = 'smart_restful';

    /**
     * permission types 'admin'|'branch'|'own'|'guest'
     *
     * @var string
     */
    protected $defaultPermissionType = 'admin';

    /**
     * permission types 'admin'|'branch'|'own'|'guest'
     *
     * @var string
     */
    protected $groupTitle = null;

    /**
     * @var null
     */
    protected $defaultLocaleClass = null;

    /**
     * @var string
     */
    protected $localPrefix = 'controller';

    /**
     * @var int
     */
    protected $DEFAULT_RESULT_PER_PAGE = 15;

    /**
     * @var int
     */
    protected $DEFAULT_PAGE_NUMBER = 1;

    /**
     * @var array
     */
    protected $pivotFields = [];

    /**
     * @var array
     */
    protected $uniqueFields = [];

    /**
     * @var bool|string
     */
    protected $modelName;

    /**
     * @var string
     */
    protected $messagePrefix = 'messages.api.v1.';

    /**
     * this model
     */
    protected $model;

    /**
     * index request validator rules
     *
     * @var array
     */
    protected $indexValidateArray = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $indexLoad = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $editLoad = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $updateLoad = [
        //
    ];

    /**
     * array of relationship for eager loading
     *
     * @var array
     */
    protected $storeLoad = [
        //
    ];

    /**
     * store request validator rules
     *
     * @var array
     */
    protected $storeValidateArray = [
        //
    ];

    /**
     * update request validator rules
     *
     * @var array
     */
    protected $updateValidateArray = [
        //
    ];

    /**
     * @var array
     */
    protected $middlewareParams = [];

    /**
     * defaultController constructor.
     */
    public function __construct()
    {
        // init controller
        $this->initController();

        // set local language
        $this->setLocale();
    }

    /**
     * @return mixed
     */
    abstract public function initController();

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return string
     */
    public function index(Request $request)
    {
        // handle permission
//        list($request, $filters) = $this->handlePermission(__FUNCTION__, $request);

        // create response model
        $response = new ResponseModel();

        $pageSize = $this->DEFAULT_RESULT_PER_PAGE;

        $pageNumber = 1;

        //set default pagination

        //set page size
        if (!isset($request->toArray()['page']['size'])) {
            $pageSize = $this->DEFAULT_RESULT_PER_PAGE;
        } elseif (($request->get('page')['size']) == 0) {
            $pageSize = $this->DEFAULT_RESULT_PER_PAGE;
        } else {
            $pageSize = $request->get('page')['size'];
        }

        //set page number
        if (!isset($request->get('page')['number'])) {
            $pageNumber = $this->DEFAULT_PAGE_NUMBER;
        } elseif (($request->get('page')['number']) == 0) {
            $pageNumber = $this->DEFAULT_PAGE_NUMBER;
        } else {
            $pageNumber = $request->get('page')['number'];
        }
        $request['page'] = $pageNumber;


        //set default ordering
        if (isset($request->toArray()['order_by'])) {
            if (is_null($request['order_by'])) {
                $request['order_by'] = "{\"field\":\"id\",\"operator\":\"Desc\"}";
            }
        }

        $validationErrors = $this->checkRequestValidation($request, $this->indexValidateArray);
        if ($validationErrors != null) {
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($validationErrors->toArray()));
            }
            $response->setMessage($this->getTrans(__FUNCTION__, 'validation_failed'));
            $response->setStatusCode(401);
            return SmartResponse::response($response);
        }

        try {
            $data = $request->get('query') != null ?
                $this->model
                    ->whereKey(collect($this->model
                        ->search(($request->get('query')))
                        ->raw())->get('ids')) :
                $this->model;

            if (array_key_exists('file', $request->toArray())) {
                //TODO add relation on top if here and create a tree flatter array in array helper
                //return (new ExcelHelper())->setOptions([
                //    'store_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                //    'download_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                //])->table($data->get()->toArray())->createExcelFile()->download();
            }

            // load relations
            if (count($this->indexLoad) > 0) {
                $data = $data->with($this->indexLoad);
            }

            // filters by
            if (!is_null($request->get('filters'))) {
                $data = (new QueryHelper())->deepFilter($data, (new RequestHelper())->getCollectFromJson($request['filters']));
            }

            // order by
            if (!is_null($request->get('order_by'))) {
                $data = (new QueryHelper())->orderBy($data, (new RequestHelper())->getCollectFromJson($request['order_by']));
            }

            // return response
            $response->setData(collect($data->paginate($pageSize)));
            $response->setMessage($this->getTrans('index', 'successful'));
            return SmartResponse::response($response);

        } catch (QueryException $exception) {

            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setError($exception->getCode());
            $response->setMessage($this->getTrans('index', 'failed'));
            return SmartResponse::response($response);
        }
    }

    public function checkRequestValidation(Request $request, $validationArray)
    {
        $requestParams = $request->toArray();
        $validator = Validator::make($request->all(), $validationArray);
        if ($validator->fails()) {
            return $validator->errors();
        }
        return null;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function message($status)
    {
        $key = $this->messagePrefix . $this->modelName . '.' . debug_backtrace()[1]['function'] . '.' . $status;
        return $this->getMessageFromFile($key);
    }

    /**
     * @param $key
     * @return mixed
     */
    public function getMessageFromFile($key)
    {
        return config($key);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        // Create Response Model
        $response = new ResponseModel();

        // return response
        $response->setData(collect($this->model->getFillable()));
        $response->setMessage($this->getTrans('create', 'successful'));
        return SmartResponse::response($response);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
//        // handle permission
//        $request = $this->handlePermission(__FUNCTION__, $request);

        // Create Response Model
        $response = new ResponseModel();

        if (!isset($userId)) {
            $userId = 1;
        }

        //add author id into the request if doesn't exist
        if (is_null($request->get('author_id'))) {
            $request['author_id'] = $userId;
        }

        //add user id into the request if doesn't exist
        if (is_null($request->get('user_id'))) {
            $request['user_id'] = $userId;
        }

        $validationErrors = $this->checkRequestValidation($request, $this->storeValidateArray);

        if ($validationErrors != null) {
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($validationErrors->toArray()));
            }
            $response->setMessage($this->getTrans(__FUNCTION__, 'validation_failed'));
            $response->setError(99);
            return SmartResponse::response($response);

        }

        try {
            // get result of model creation
            $result = $this->model->create($request->all());
            // sync many to many relation
            foreach ($this->pivotFields as $pivotField) {
                if (collect($request[$pivotField])->count()) {
                    $pivotField = (new StringHelper())->toCamel($pivotField);
                    $this->model->find($result['id'])->$pivotField()->sync(json_decode($request[$pivotField]));
                }
            }
            $response->setMessage($this->getTrans('store', 'successful'));


            $response->setData($this->model
                ->where($this->model->getKeyName(), $result['id'])
                ->with(collect($this->updateLoad)->count() == 0 ? $this->indexLoad : $this->updateLoad)
                ->get());

        } catch (QueryException $exception) {
            $response->setError($exception->getCode());
            $response->setMessage($this->getTrans('store', 'failed'));
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }
        }
        return SmartResponse::response($response);
    }

    /**
     * Display the specdefaultied resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $filters = [];
//        // handle permission
//        $filters = $this->handlePermission(__FUNCTION__);

        // Create Response Model
        $response = new ResponseModel();

        // try to get data
        try {
            $response->setMessage($this->getTrans('show', 'successful'));

            // add filter to get desired record
            array_push($filters,
                [$this->model->getKeyName(), '=', $id]
            );
            $data = $this->model
                ->where($filters)
                ->get();
            $response->setData($data);

            // catch exception
        } catch (Exception $exception) {
            $response->setError($exception->getCode());
            $response->setMessage($this->getTrans('show', 'failed'));
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }
        }
        return SmartResponse::response($response);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        $filters = [];
//        // handle permission
//        $filters = $this->handlePermission(__FUNCTION__);

        // Create Response Model
        $response = new ResponseModel();

        try {
            $response->setMessage($this->getTrans('edit', 'successful'));

            // add filter to get desired record
            array_push($filters,
                [$this->model->getKeyName(), '=', $id]
            );
            $data = $this->model
                ->where($filters)
                ->with(collect($this->editLoad)->count() == 0 ? $this->indexLoad : $this->editLoad)
                ->get();
            $response->setData($data);

        } catch (ModelNotFoundException $exception) {
            $response->setError($exception->getCode());
            $response->setMessage($this->getTrans('edit', 'failed'));
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }

        }

        return SmartResponse::response($response);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Handle permission
//        $request = $this->handlePermission(__FUNCTION__,$request);
//        if ($request->has('permission_filters')){
//            $filters = $request['permission_filters'];
//        }else{
        $filters = [];
//        }

        // Create Response Model
        $response = new ResponseModel();

        // Filters
        array_push($filters,
            [$this->model->getKeyName(), '=', $id]
        );

        $validationErrors = $this->checkRequestValidation($request, $this->updateValidateArray);
        if ($validationErrors != null) {
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($validationErrors->toArray()));
            }
            $response->setMessage($this->getTrans(__FUNCTION__, 'validation_failed'));
            $response->setError(99);
            return SmartResponse::response($response);

        }

        try {
            // sync many to many relation
            foreach ($this->pivotFields as $pivotField) {
                if (collect($request[$pivotField])->count()) {
                    $pivotMethod = (new StringHelper())->toCamel($pivotField);
                    $this->model->findOrFail($id)->$pivotMethod()->sync(json_decode($request[$pivotField], true));
                }
            }
            //get result of update
            $result = $this->model->where($filters)->firstOrFail()->update($request->all());

            // return response
            $response->setData($this->model
                ->where($filters)
                ->with(collect($this->updateLoad)->count() == 0 ? $this->indexLoad : $this->updateLoad)
                ->get());
            $response->setMessage(
                $this->getTrans(__FUNCTION__, 'successful1') .
                $result .
                $this->getTrans(__FUNCTION__, 'successful2')
            );

        } catch (ModelNotFoundException $exception) {
            $response->setMessage($this->getTrans(__FUNCTION__, 'model_not_found'));
            $response->setError($exception->getCode());
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }

        } catch (QueryException $exception) {
            $response->setMessage($this->getTrans(__FUNCTION__, 'failed'));
            $response->setError($exception->getCode());
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }

        }

        return SmartResponse::response($response);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        // Create Response Model
        $response = new ResponseModel();

        try {
            $response->setData(collect($this->model->findOrFail($id)->delete()));
            $response->setMessage($this->getTrans('destroy', 'successful'));

        } catch (ModelNotFoundException $exception) {
            $response->setMessage($this->getTrans('destroy', 'successful'));
            $response->setError($exception->getCode());
            if (env('APP_DEBUG', false)) {
                $response->setData(collect($exception->getMessage()));
            }

        }

        return SmartResponse::response($response);
    }

    /**
     * @param $method
     * @param $status
     * @return array|\Illuminate\Contracts\Translation\Translator|null|string
     */
    public function getTrans($method, $status)
    {
        if (is_null($this->defaultLocaleClass)) {
            $className = Arr::last(explode('\\', get_class($this)));
        } else {
            $className = $this->defaultLocaleClass;
        }
        return trans(
            'laravel_smart_restful::' .
            $this->localPrefix . '.' .
            ($className === '' ? '' : ($className . '.')) .
            $method . '.' .
            $status
        );
    }

    /**
     * set locale
     */
    public function setLocale()
    {
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            app('translator')->setLocale($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
    }

    /**
     * @param $functionName
     * @param Request|null $request
     * @param null $id
     * @param array $params
     * @return mixed
     */
    public function handlePermission($functionName, Request $request = null, $id = null, $params = [])
    {
        // define filter
        $filters = null;

        // init permission
        $request = $this->initPermissionTag($request);

        // init method name with
        $methodName =
            'handle' .
            (new StringHelper())->upperFirstLetter($functionName) .
            (new StringHelper())->upperFirstLetter($this->defaultPermissionType) .
            'Permission';

        // return response
        return $this->$methodName($request, $id);
    }

    /**
     * @param Request $request
     * @param $id
     * @return Request
     */
    public function handleIndexAdminPermission(Request $request, $id = null): Request
    {
        return $request;
    }

    /**
     * @param Request $request
     * @param null $id
     * @return Request
     */
    public function handleIndexBranchPermission(Request $request, $id = null): Request
    {
        $request = $this->getFilters($request);
        $request = $this->putGroupFilter($request);
        $request = $this->putJsonFilters($request);
        return $request;
    }

    /**
     * @param Request $request
     * @param $id
     * @return Request
     */
    public function handleIndexOwnPermission(Request $request, $id = null): Request
    {
        $request = $this->getFilters($request);
        $request = $this->putOwnerFilter($request,auth()->id());
        $request = $this->putGroupFilter($request);
        $request = $this->putJsonFilters($request);
        return $request;
    }

    public function handleIndexGuestPermission(Request $request, $id)
    {
        $request = $this->getFilters($request);
        $request = $this->putOwnerFilter($request);
        $request = $this->putGroupFilter($request);
        $request = $this->putJsonFilters($request);
        return $request;
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreAdminPermission(Request $request, $id)
    {
        $request['group_id'] = null;

        $request['owner_id'] = auth()->id();

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreBranchPermission(Request $request, $id)
    {
        // check for group of user
        if (!is_null($this->groupTitle)) {
            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();
            $request['group_id'] = $group['id'];
        }

        $request['owner_id'] = auth()->id();

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreOwnPermission(Request $request, $id)
    {
        // check for group of user
        if (!is_null($this->groupTitle)) {
            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();
            $request['group_id'] = $group['id'];
        }

        $request['owner_id'] = auth()->id();

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleStoreGuestPermission(Request $request, $id)
    {
        $request['owner_id'] = null;

        return [$request, null];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowAdminPermission(Request $request, $id)
    {
        $filters = [];
        return [$request, $filters];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowBranchPermission(Request $request, $id)
    {
        $filters = [];
        array_push($filters,
            ["owner_id", "=", auth()->id()]
        );
        if (!is_null($this->groupTitle)) {

            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();

            // put group_id filter
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }
        return [$request, $filters];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowOwnPermission(Request $request, $id)
    {
        $filters = [];
        array_push($filters,
            ["owner_id", "=", auth()->id()]
        );

        // check for group of user
        if (!is_null($this->groupTitle)) {

            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();

            // put group_id filter
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }

        return [$request, $filters];
    }

    /**
     * @param Request $request
     * @param $id
     * @return array
     */
    public function handleShowGuestPermission(Request $request, $id)
    {
        $filters = [];
        array_push($filters,
            ["owner_id", "=", auth()->id()]
        );

        // check for group of user
        if (!is_null($this->groupTitle)) {

            // get group model
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();

            // put group_id filter
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }

        return [$request, $filters];
    }

    public function handleEditAdminPermission(Request $request, $id)
    {
        $filters = [];
        return [$request, $filters];
    }

    public function handleEditBranchPermission(Request $request, $id)
    {
        $filters = [];
        array_push(
            $filters,
            ["owner_id", "=", auth()->id()]
        );
        if (!is_null($this->groupTitle)) {
            $group = new Group();
            $group = $group->where('title', $this->groupTitle)->first();
            array_push($filters,
                ['group_id', '=', $group['id']]);
        }
        return [$request, $filters];
    }

    /**
     * @param $functionName
     * @param Request|null $request
     * @param $params
     * @return array|Request
     */
    public function handleOwnPermission($functionName, Request $request = null, $params)
    {
        switch ($functionName) {
            case 'index':
            case 'store':
            case 'show':
            case 'edit':
                $filters = [];
                array_push($filters,
                    ["owner_id", "=", auth()->id()]
                );

                // check gor group of user
                if (!is_null($this->groupTitle)) {
                    // get group model
                    $group = new Group();
                    $group = $group->where('title', $this->groupTitle)->first();

                    // put group_id filter
                    array_push($filters,
                        ['group_id', '=', $group['id']]);
                }

                return $filters;
            case 'create':
            case 'update':

                $filters = [];
                // put to filter
                array_push($filters,
                    ["owner_id", "=", auth()->id()]
                );

                // put to request
                $request['owner_id'] = auth()->id();

                // check for group of user
                if (!is_null($this->groupTitle)) {
                    // get group model
                    $group = new Group();
                    $group = $group->where('title', $this->groupTitle)->first();

                    // put group_id filter
                    array_push($filters,
                        ['group_id', '=', $group['id']]);

                    // put to request
                    $request['group_id'] = $group['id'];
                }

                $request['permission_filters'] = $filters;
                return $request;
            case 'destroy':
                $filters = [];
                array_push($filters,
                    ["owner_id", "=", auth()->id()],
                    ["group_id", "=", $this->groupTitle]
                );
                return $filters;
            default:
                return $params;
        }
    }

    /**
     * @param array $filters
     * @param $key
     * @param $operator
     * @param $value
     * @return array
     */
    public function addFilter(array $filters, $key, $operator, $value)
    {
        if (count(explode('.', $key)) == 1 && $this->filedExist($key)) {
            array_push($filters,
                [
                    'key' => $key,
                    'operator' => $operator,
                    'value' => $value,
                ]
            );
        }
        return $filters;
    }

    /**
     * @param $key
     * @return bool
     */
    public function filedExist($key)
    {
        return in_array($key, $this->model->getFillable());
    }

    /**
     * @return string
     */
    public function getLocalPrefix(): string
    {
        return $this->localPrefix;
    }

    /**
     * @param string $localPrefix
     */
    public function setLocalPrefix(string $localPrefix)
    {
        $this->localPrefix = $localPrefix;
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function initPermissionTag(Request $request): Request
    {
        if (!is_null($request)) {
            if ($request->has($this->getRequestTagName('permission_type'))) {
                $this->defaultPermissionType = $request[$this->getRequestTagName('permission_type')];
            }
        }
        return $request;
    }

    public function getRequestTagName($name): String
    {
        return $this->shortTagName . '_' . $name;
    }

    /**
     * get filter if exist
     *
     * @param Request $request
     * @return Request
     */
    public function getFilters(Request $request): Request
    {
        // check for exist filters in request and get filters
        if (isset($request['filters'])) {
            $filters = json_decode($request['filters'], true);
        } else {
            $filters = [];
        }

        $request[$this->getRequestTagName('filter')] = $filters;

        return $request;
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function putGroupFilter(Request $request): Request
    {
        if (!is_null($this->groupTitle)) {
            $request[$this->getRequestTagName('filter')] = $this->addFilter(
                $request[$this->getRequestTagName('filter')],
                'group.title',
                '=',
                $this->groupTitle
            );
        }
        return $request;
    }

    /**
     * @param Request $request
     * @param $userId
     * @return Request
     */
    public function putOwnerFilter(Request $request,$userId = null): Request
    {
        $request[$this->getRequestTagName('filter')] =
            $this->addFilter(
                $request[$this->getRequestTagName('filter')],
                'owner_id',
                '=',
                $userId
            );
        return $request;
    }

    /**
     * @param Request $request
     * @return Request
     */
    public function putJsonFilters(Request $request): Request
    {
        $request['filters'] = json_encode($request[$this->getRequestTagName('filter')]);
        return $request;
    }

    /**
     * @return mixed
     */
    public function getModel():Model
    {
        return $this->model;
    }

    /**
     * @param Model $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function successfulResponse(Request $request): JsonResponse
    {
        $response = new ResponseModel();
        if (!is_null($request->get($this->dataResponseTag))) {
            $response->setData(collect($request[$this->dataResponseTag]));
        };
        $response->setMessage($this->getTrans(
            $request->get($this->functionNameResponseTag),
            'successful'
        ));
        return SmartResponse::response($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function failedResponse(Request $request): JsonResponse
    {
        $response = new ResponseModel();
        $response->setStatus(false);
        $this->setResponseData($request, $response);
        $response->setMessage($this->getTrans(
            $request->get($this->functionNameResponseTag),
            'failed'
        ));
        return SmartResponse::response($response);
    }

    /**
     * @param Request $request
     * @param $response
     */
    public function setResponseData(Request $request, $response)
    {
        if (!is_null($request->get($this->dataResponseTag))) {
            $data = $request->get($this->dataResponseTag);
            if (method_exists($data, 'getCode')) {
                $response->setError($data->getCode());
            }
            if (method_exists($data, 'getResponse')) {
                $response->setData(collect(json_decode(
                    $data->getResponse()
                        ->getBody()
                        ->getContents()
                )));
            }
        };
    }
}