<?php

namespace App\Http\Controllers\Toplevel;

use App\Exports\MekanikExport;
use App\Http\Controllers\Controller;
use App\Imports\MekanikImport;
use App\Mekanik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Datatables;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PDF;
use DB;
use Session;

class MekanikController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $mekanik = Mekanik::all();
        return view('pages.toplevel.mekanik.index', [
            'mekaniks' => $mekanik
        ]);
    }

    // api data users
    public function apimekanik()
    {
        $mekanik = Mekanik::orderBy('id', 'DESC')->get();
        return DataTables::of($mekanik)->addColumn('action', function ($mekanik) {
            return '' .
                '&nbsp;<a href="#mymodal" data-remote="' . route('toplevel.mekanik.show', ['mekanik' => $mekanik->id]) . '" data-toggle="modal" data-target="#mymodal" data-title=" ' . $mekanik->name . ' " class="btn btn-info btn-flat btn-sm"><i class="fa fa-eye"></i></a>';
        })->rawColumns(['action'])->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validation = array(
            "name" => "required|unique:mekaniks|max:255",
            "email" => "required|unique:mekaniks",
            "address" => "required",
            "no_telphone" => "required|numeric|min:0"
        );
        $messages = array(
            "name.required" => "field Name tidak Boleh Kosong!",
            "name.unique" => "Name Mekanik Sudah Ada",
            "address.required" => "isi alamat dengan lengkap",
            "no_telphone.required" => "field No Phone tidak boleh kosong",
            "no_telphone.numeric" => "inputan berupa angka"
        );

        $errors = Validator::make($request->all(), $validation, $messages);
        if ($errors->fails()) {
            return response()->json(['errors' => $errors->getMessageBag()->toArray()]);
        }

        $mekanik = new Mekanik;
        $mekanik->name = $request->get('name');
        $mekanik->email = $request->get('email');
        $mekanik->address = $request->get('address');
        $mekanik->no_telphone = $request->get('no_telphone');
        $mekanik->status = "ACTIVE";

        if ($request->file('image')) {
            $image_path = $request->file('image')->store('image-mekanik', 'public');
            $mekanik->image = $image_path;
        } else {
            $mekanik->image = null;
        }

        $mekanik->save();
        return response()->json(['success' => 'Data successfully Created!']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $mekanik = Mekanik::findOrFail($id);

        return view('pages.toplevel.mekanik.show')->with([
            'mekanik' => $mekanik
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $mekaniks = Mekanik::findOrFail($id);
        return view('pages.toplevel.mekanik.edit', ['mekanik' => $mekaniks]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $mekanik = Mekanik::findOrFail($id);
        $validation = array(
            "name" => ['required', Rule::unique('mekaniks')->ignore($mekanik->name, 'name')],
            "email" => ['required', Rule::unique('mekaniks')->ignore($mekanik->email, 'email')],
            "no_telphone" => "required|numeric|min:0",
            "address" => "required",
        );
        $messages = array(
            "name.required" => "field Name tidak Boleh Kosong!",
            "name.unique" => "Name Mekanik Sudah Ada",
            "email.required" => "field email tidak boleh kosong",
            "email.unique" => "email sudah terdaftar",
            "address.required" => "isi alamat dengan lengkap",
            "no_telphone.required" => "field No Phone tidak boleh kosong",
            "no_telphone.numeric" => "inputan berupa angka"
        );

        $mekanik->name = $request->get('name');
        $mekanik->email = $request->get('email');
        $mekanik->no_telphone = $request->get('no_telphone');
        $mekanik->address = $request->get('address');
        $mekanik->status = $request->get('status');

        $mekanik->save();
        return redirect()->route('toplevel.mekanik.edit', $id)->with('status', 'Mekanik status successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $mekaniks = Mekanik::findOrFail($id);
        $mekaniks->delete();

        return response()->json(['status' => 'Supplier deleted successfully']);
    }

    public function setStatus(Request $request, $id)
    {
        $mekanik = Mekanik::findOrFail($id);
        $mekanik->status = $request->status;

        $mekanik->save();

        return redirect()->route('toplevel.mekanik.index')
            ->with('status', 'Status successfully updated');
    }

    public function exportPdf()
    {
        $year_today = Carbon::now()->format('Y');
        $mekaniks = Mekanik::all();
        $pdf = PDF::loadView('pages.toplevel.export_data.mekanik_pdf', [
            'mekaniks' => $mekaniks, 'year_today' => $year_today
        ]);
        return $pdf->stream('mekanik.pdf');

    }

    public function exportExcel()
    {
        return Excel::download(new MekanikExport, 'listmekanik.xlsx');
    }

    public function importExcel(Request $request)
    {
        $this->validate($request, [
            'file' => 'required|mimes:csv,xls,xlsx'
        ]);

            // menangkap file excel
        $file = $request->file('file');

        // membuat nama file unik
        $nama_file = rand().$file->getClientOriginalName();

        // upload ke folder file_siswa di dalam folder public
        $file->move('file_mekanik',$nama_file);

        // import data
        Excel::import(new MekanikImport, public_path('/file_mekanik/'.$nama_file));

        // notifikasi dengan session
        Session::flash('sukses','Data mekanik Berhasil Diimport!');

        // alihkan halaman kembali
        return redirect()->route('toplevel.mekanik.index');
    }
}
