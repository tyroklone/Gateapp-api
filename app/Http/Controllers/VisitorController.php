<?php

namespace App\Http\Controllers;

use App\Visitor_History;
use App\Visitor;
use App\ScheduledVisit;
use Exeception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\QrCodeGenerator;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class VisitorController extends Controller
{

    /**
     * @var string $user
     * @access protected
     */
    protected $user;

    public function __construct()
    {
        $this->user = auth()->user();
    }

    /**
     * Gets all visitors for a signed in user
     */
    public function residentVisitor(Request $request)
    {
        $visitors = Visitor::where('user_id', $this->user->id)->get();

        if ($visitors->isEmpty()) {
            return response()->json([
                'Message'   => "No Visitors found for this user",
                'status' => false
            ], 404);
        } else {
            // send response with the visitors' details
            return response()->json([
                'visitors' => $visitors->count(),
                'visitor'   => $visitors,
                'status' => true
            ], 200);
        }
    }

    /**
     * Admin gets all visitors
     *
     * @param  int $page number of pages for pagination
     * @return JSON
     */
    public function index(Request $request)
    {
        // get number of visitors to be fetched
        $per_page = $request->query('per_page');

        // return the requested number of visitors.
        // if there was no pagination set by the query,
        // limit the response to 15 data set
        $visitors = Visitor::paginate($per_page);

        // send response with the visitors' details
        return response()->json([
            'visitors' => $visitors,
            'status' => true
        ], 200);
    }

    public function residentHistory(Request $request)
    {
        $visitors = Visitor_History::where('user_id', $this->user->id)->with('user')->with('visitor')->get();

        if ($visitors->isEmpty()) {
            return response()->json([
                'Message'   => "No Visitors found for this user",
                'status' => false
            ], 404);
        } else {
            // send response with the visitors' details
            return response()->json([
                'visitors' => $visitors->count(),
                'visitor_details'   => $visitors,
                'status' => true
            ], 200);
        }
    }

    /**
     * Get a single visitor
     *
     * @param  int $id the visitor id
     * @return JSON
     */
    public function show($id)
    {
        // retrieve the visitor's detials with the id
        $res = $this->user->visitors()->find($id);

        // output an error if the id is not found
        if (!$res) {
            return response()->json([
                'status'  => false,
                'message' => 'Record not found!'
            ], 404);
        }

        // send response
        return response()->json([
            'visitor' => $res,
            'status'  => true
        ], 200);
    }

    /**
     * Create a new visitor
     *
     * @param  obj $request an instance of the Request::class
     * @return JSON
     */
    public function store(
        Request $request,
        QrCodeGenerator $qr,
        ImageController $image
    ) {
        // validate the posted data
        $this->validate($request, [
            'name'              => ['required', 'regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/'],
            'arrival_date'      => 'required|date_format:Y-m-d',
            'car_plate_no'      => 'string|nullable',
            'purpose'           => 'string',
            'visitor_group'     => 'string',
            'visiting_period'     => 'required|string',
            'phone_no'          => 'string',
            'description'       => 'string|nullable',
        ]);

        $randomToken = Str::random(6);

        DB::beginTransaction();

        try {
            $visitor = new Visitor();
            $visitor->name = $request->name;
            $visitor->arrival_date = $request->arrival_date;
            $visitor->car_plate_no = $request->car_plate_no ?? '';
            $visitor->phone_no = $request->phone_no ?? '';
            $visitor->purpose = $request->purpose ?? '';
            $visitor->visitor_group = $request->visitor_group ?? '';
            $visitor->status  = 1;
            $visitor->visit_count  = 1;
            $visitor->user_id = $this->user->id;
            $visitor->visiting_period = $request->visiting_period;
            $visitor->description = $request->description ?? '';
            $visitor->qr_code = $randomToken;

            //Generate qr image
            $qr_code = $qr->generateCode($randomToken);

            //Upload image
            if ($request->hasFile('image')) {
                $data = $this->upload($request, $image);
                if ($data['status_code'] !=  200) {
                    return response()->json($data, $data['status_code']);
                }
                $visitor->image = $data['image'];
            } else {
                $data = null;
                $visitor->image = 'noimage.jpg';
            }


            //Save Visitor
            $this->user->visitors()->save($visitor);

            //if operation was successful save commit save to database
            DB::commit();

            // send response
            return response()->json([
                'status'      => true,
                'image_info'  => $data,
                'message'     => 'Visitor successfully added',
                'visitor'     => $visitor,
                'image_info'  => $data,
                'qr_image_src' => $qr_code
            ], 200);
        } catch (Exception $e) {
            //if any operation fails, Thanos snaps finger - user was not created rollback what is saved
            DB::rollBack();
            $res['status']   = false;
            $res['message']  = 'Error, Visitor not created, please try again';
            $res['hint']     = $e->getMessage();
            return response()->json($msg, 501);
        }
    }

    /**
     * Update a single visitor
     *
     * @param  obj $request an instance of Request::class
     * @param  int $id      the visitor id
     * @return JSON
     */
    public function update(
        $id,
        Request $request,
        ImageController $image
    ) {
        // gets the visitor's record from the database
        $visitor = $this->user->visitors()->find($id);

        // output an error if the id is not found
        if (!$visitor) {
            return response()->json([
                'message' => 'This visitor could not be found, check and try again!'
            ], 404);
        }

        // validate the posted data
        $this->validate($request, [
            'name'              => ['regex:/^([a-zA-Z]+)(\s[a-zA-Z]+)*$/'],
            'arrival_date'      => 'date_format:Y-m-d',
            'car_plate_no'      => 'string',
            'phone_no'          => 'string',
            'purpose'           => 'string',
            'visiting_period'   => 'string',
            'description'       => 'string',
        ]);

        DB::beginTransaction();

        try {
            $visitor->name = $request->name ?? $visitor->name;
            $visitor->arrival_date = $request->arrival_date ?? $visitor->arrival_date;
            $visitor->car_plate_no = $request->car_plate_no ?? $visitor->car_plate_no;
            $visitor->phone_no = $request->phone_no ?? $visitor->phone_no;
            $visitor->purpose = $request->purpose ?? $visitor->purpose;
            $visitor->visitor_group = $request->visitor_group ?? '';
            $visitor->visiting_period = $request->visiting_period ?? $visitor->visiting_period;
            $visitor->description = $request->description ?? $visitor->description;

            // Upload updated image
            //Upload image
            if ($request->hasFile('image')) {
                $data = $this->upload($request, $image, $visitor);
                if ($data['status_code'] !=  200) {
                    return response()->json($data, $data['status_code']);
                }
                $visitor->image = $data['image'];
            } else {
                $data = null;
                $visitor->image = 'noimage.jpg';
            }

            //Save Visitor
            $visitor->save();

            //if operation was successful save commit save to database
            DB::commit();

            // send response
            return response()->json([
                'status'      => true,
                'message'     => "Visitor's data has been updated successfully!",
                'visitor'     => $visitor,
                'image_info'  => $data
            ], 200);
        } catch (Exception $e) {
            //if any operation fails, rollback what is saved
            DB::rollBack();
            $res['status']   = false;
            $res['message']  = "Error, Visitor's data could not be updated, please try again.";
            $res['hint']     = $e->getMessage();
            return response()->json($msg, 501);
        }
    }

    /**
     * Delete a single visitor
     *
     * @param  int $id the visitor id
     * @return JSON
     */
    public function destroy($id)
    {
        // retrieve the visitor's detials with the id
        $visitor = $this->user->visitors()->find($id);

        // output an error if the visitor id is not found
        if (!$visitor) {
            return response()->json(['message' => 'This visitor could not be found!'], 400);
        }

        // delete the requested visitor and send a response
        elseif ($visitor->delete()) {
            return response()->json([
                'status' => true,
                'message' => 'Visitor has been deleted successfully!',
            ], 200);
        } else {
            // if delete action fails, send a response
            return response()->json([
                'status' => false,
                'message' => 'Sorry, this visitor could not be deleted!',
            ], 500);
        }
    }
    public function upload($request, $image, $table = null)
    {
        $user = Auth::user();

        $this->validate($request, [
            'image' => "image|max:4000",
        ]);
        //Image Engine
        $res = $image->imageUpload($request, $table);
        return $res;
    }

    public function fetchEstateVisitHistory($id)
    {
        $visitors = DB::table('visitors_history')->get()->where('user_id',$id);
        if($visitors && $visitors != []) {
            $res['status']  = true;
            $res['message'] = 'Estate visit history';
            $res['visitors'] = $visitors;
            return response()->json($res, 200);
        }else {
            $res['status']  = false;
            $res['message'] = 'No Record found';
            return response()->json($res, 404);
        }
    }

    public function fetchAllVisitHistory()
    {
        $visitors = DB::table('visitors_history')->orderBy('user_id')->get();
        if($visitors) {
            $res['status']  = true;
            $res['message'] = 'Estate visitors';
            $res['visitors'] = $visitors;
            return response()->json($res, 200);
        }else {
            $res['status']  = false;
            $res['message'] = 'No Record found';
            return response()->json($res, 404);
        }
    }


    // fetch all visitors to an estate
    public function fetchEstateVisitors($id)
    {
        $visitors = DB::table('visitors')->get()->where('user_id',$id);
        if($visitors) {
            $res['status']  = true;
            $res['message'] = 'Estate visitors';
            $res['visitors'] = $visitors;
            return response()->json($res, 200);
        }else {
            $res['status']  = false;
            $res['message'] = 'No Record found';
            return response()->json($res, 404);
        }
    }

    // fetch all visitors, requires super admin privileges
    public function fetchSuperAdminVisitors()
    {
        // $visitors = \App\Visitor::all();
        $visitors = DB::table('visitors')->orderBy('user_id')->get();
        if($visitors) {
            $res['status']  = true;
            $res['message'] = 'All visitors (All)';
            $res['visitors'] = $visitors;
            return response()->json($res, 200);
        }else {
            $res['status']  = false;
            $res['message'] = 'No Record found';
            return response()->json($res, 404);
        }
    }

    public function schedule($id, Request $request, QrCodeGenerator $qr)
    {
        $visitor = $this->user->visitors()->find($id);
        if ($visitor->status == 1) {
            return response()->json([
                'message' => 'Visitor have not checked out, you cannot schedule a visit.'
            ], 404);
        }
        $this->validate($request, [
            'arrival_date'      => 'required|date_format:Y-m-d',
            'car_plate_no'      => 'string|nullable',
            'purpose'           => 'string',
            'visiting_period'     => 'required|string',
            'phone_no'          => 'string',
            'description'       => 'string|nullable',
        ]);
        $randomToken = Str::random(6);
        DB::beginTransaction();
        try {
            $scheduled = new ScheduledVisit;
            $scheduled->visitor_id = $visitor->id;
            $scheduled->user_id = $visitor->user_id;
            $visitor->arrival_date = $request->arrival_date;
            $visitor->car_plate_no = $request->car_plate_no ?? $visitor->car_plate_no;
            $visitor->phone_no = $request->phone_no ?? $visitor->phone_no;
            $visitor->purpose = $request->purpose ?? $visitor->purpose;
            $visitor->status  = 1;
            $visitor->visit_count++;
            $visitor->user_id = $this->user->id;
            $visitor->visiting_period = $request->visiting_period ?? $visitor->visiting_period;
            $visitor->description = $visitor->description ?? $request->description;
            $visitor->qr_code = $randomToken;
            $visitor->image =  $visitor->image;
            $qr_code = $qr->generateCode($randomToken);
            $scheduled->save();
            $this->user->visitors()->save($visitor);

            DB::commit();
            return response()->json([
                'status'      => true,
                'message'     => 'You have successfully scheduled a visit for ' . $visitor->name,
                'visitor'     => $visitor,
                'qr_image_src' => $qr_code
            ], 200);
        } catch (Exeception $e) {
            DB::rollBack();
            $res['status']   = false;
            $res['message']  = 'Something went wrong, please try again';
            $res['hint']     = $e->getMessage();
            return response()->json($res, 501);
        }
    }
    public function getScheduled()
    {
        $scheduled = ScheduledVisit::where('user_id', $this->user->id)->with('visitor')->get();
        if (!$scheduled) {
            return response()->json(['message' => 'You have no scheduled visits'], 400);
        }
        return response()->json(['message' => 'All scheduled visits for a user', $scheduled]);
    }
    public function deleteScheduled($id)
    {
        $scheduled = ScheduledVisit::where('visitor_id',  $id)
            ->where('user_id', $this->user->id)->with('visitor')->first();
        $scheduled->visitor->status = 0;
        $scheduled->visitor->visit_count--;
        $scheduled->visitor->save();
        $scheduled->delete();

        $res['message']    = "Scheduled visit deleted";
        return response()->json($res, 200);
    }
}
