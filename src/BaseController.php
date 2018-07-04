<?php

namespace Alive2212\LaravelSmartRestful;

use Alive2212\ExcelHelper\ExcelHelper;
use Alive2212\LaravelQueryHelper\QueryHelper;
use Alive2212\LaravelRequestHelper\RequestHelper;
use Alive2212\LaravelSmartResponse\ResponseModel;
use Alive2212\LaravelSmartResponse\SmartResponse\SmartResponse;
use Alive2212\LaravelStringHelper\StringHelper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\Facades\Validator;


abstract class BaseController extends Controller
{
    /**
     * to use this class
     * create message list as messages in message file
     * override __constructor and define your model
     * define your rules for index,store and update
     */

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

    protected $middlewareParams = [];

    /**
     * defaultController constructor.
     */
    public function __construct()
    {
//        dd("I have closest relationship with all US celebrities");
        $this->initController();
    }

    abstract public function initController();

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return string
     */
    public function index(Request $request)
    {
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
            $pageNumber = $this->DEFAULT_RESULT_PER_PAGE;
        } elseif (($request->get('page')['number']) == 0) {
            $pageNumber = $this->DEFAULT_RESULT_PER_PAGE;
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

            // return response
            $response->setData(collect($validationErrors->toArray()));
            $response->setMessage("Validation Failed");
            $response->setStatus(false);
            $response->setError(99);
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
                return (new ExcelHelper())->setOptions([
                    'store_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                    'download_format' => $request->get('file') == null ? 'xls' : $request->get('file'),
                ])->table($data->get()->toArray())->createExcelFile()->download();
            }

            // load relations
            if (count($this->indexLoad) > 0) {
                $data = $data->with($this->indexLoad);
            }

            // filters by
            if (isset($request->toArray()['filters'])) {
                $data = (new QueryHelper())->deepFilter($data, (new RequestHelper())->getCollectFromJson($request['filters']));
            }

            // order by
            if (isset($request->toArray()['order_by'])) {
                $data = (new QueryHelper())->orderBy($data, (new RequestHelper())->getCollectFromJson($request['order_by']));
            }

            // return response
            $response->setData(collect($data->setPerPage($pageSize)->paginate()));
            $response->setMessage("Successful");
            return SmartResponse::response($response);

        } catch (QueryException $exception) {

            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setError($exception->getCode());
            $response->setMessage("Failed");
            $response->setStatus(false);
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
        if (is_numeric(array_search($request->getMethod(), ["POST", "PUT", "PATCH"]))) {
            $errors = new MessageBag();
            foreach ($requestParams as $requestParamKey => $requestParamValue) {
                if (is_numeric(array_search($requestParamKey, $this->uniqueFields))) {
                    if ($this->checkExistUniqueRecord($requestParamKey, $requestParamValue)) {
                        $errors->add($requestParamKey, 'This ' . $requestParamKey . ' is exist try another.');
                    }
                }
            }
            if (collect($errors)->count() > 0) {
                return $errors;
            }
        }
        return null;
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     */
    public function checkExistUniqueRecord($key, $value)
    {
        if ($this->model->where($key, $value)->count()) {
            return true;
        }
        return false;
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
        $response->setMessage("Successful");
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
                $response->setMessage(json_encode($validationErrors->getMessages()));
            }
            $response->setStatus(false);
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
            $response->setMessage('successful');
            $response->setData(collect($result->toArray()));
            $response->setStatus(true);
            return SmartResponse::response($response);
        } catch (QueryException $exception) {
            if (env('APP_DEBUG', false)) {
                $response->setMessage($exception->getMessage());
            }
            $response->setStatus(false);
            return SmartResponse::response($response);
        }
    }

    /**
     * Display the specdefaultied resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Create Response Model
        $response = new ResponseModel();

        try {
            $response->setMessage('Successful');
            $response->setData(collect($this->model->findOrFail($id)));
            return SmartResponse::response($response);

        } catch (ModelNotFoundException $exception) {
            $response->setData(collect($exception->getMessage()));
            $response->setError($exception->getCode());
            $response->setMessage('Not Found');
            $response->setStatus(false);
            return SmartResponse::response($response);
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        // Create Response Model
        $response = new ResponseModel();

        try {
            $response->setMessage('Successful');
            $response->setData($this->model
                ->where($this->model->getKeyName(), $id)
                ->with(collect($this->editLoad)->count() == 0 ? $this->indexLoad : $this->editLoad)
                ->get());
            return SmartResponse::response($response);
        } catch (ModelNotFoundException $exception) {
            $response->setData(collect($exception->getMessage()));
            $response->setError($exception->getCode());
            $response->setMessage('Failed');
            $response->setStatus(false);
            return SmartResponse::response($response);
        }
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
        // Create Response Model
        $response = new ResponseModel();

        $validationErrors = $this->checkRequestValidation($request, $this->updateValidateArray);
        if ($validationErrors != null) {

            // return response
            $response->setData(collect($validationErrors->toArray()));
            $response->setMessage("Validation Failed");
            $response->setStatus(false);
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
            $result = $this->model->findOrFail($id)->update($request->all());

            // return response
            $response->setData(collect(env('APP_DEBUG') ? $this->model->find($id) : []));
            $response->setMessage('Successful to change ' . $result . ' record');
            return SmartResponse::response($response);

        } catch (ModelNotFoundException $exception) {

            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setStatus(false);
            $response->setMessage('Not Found');

            return SmartResponse::response($response);

        } catch (QueryException $exception) {
            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setStatus(false);
            $response->setMessage('Failed');

            return SmartResponse::response($response);
        }
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
            // return response
            $response->setData(collect($this->model->findOrFail($id)->delete()));
            $response->setMessage('Successful');

            return SmartResponse::response($response);

        } catch (ModelNotFoundException $exception) {
            // return response
            $response->setData(collect($exception->getMessage()));
            $response->setMessage('Failed');
            $response->setStatus(false);
            $response->setError($exception->getCode());

            return SmartResponse::response($response);
        }
    }
}