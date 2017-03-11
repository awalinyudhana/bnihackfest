<?php

require APPPATH . '/libraries/REST_Controller.php';

/**
 * Class Users
 */
class Users extends REST_Controller
{
    /**
     * Users constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function login_post()
    {
        $this->form_validation->set_rules('phone', 'No Telepon', 'trim|required|is_natural');
        $this->form_validation->set_rules('pin', 'PIN', 'required');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );

        $phone = $this->input->post('phone');
        $pin = $this->input->post('pin');

        $data = $this->db->get_where('users', [
            'phone' => $phone,
            'pin' => $pin
        ])->row();


        $return =[];
        if (is_null($data))
        {
            $return = [
                'status' => false,
                'message' => 'Nomor Telepon atau PIN salah'
            ];
        }
        else
        {
            $return = [
                'status' => true,
                'data' => $data
            ];
        }


        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function by_id_get($id)
    {
        $user = $this->db->get_where('users', ['user_id' => $id])->row_array();
        $user['profile_path'] = site_url('uploads/'. $user['profile']);

        $return = [
            'status' => true,
            'data' => $user
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function pickup_get($id)
    {
        $this->db->update('users', ['alert' => true], ['user_id' => $id]);
        $return = [
            'status' => true,
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);

    }

    public function transfer_post()
    {
        $this->form_validation->set_rules('phone', 'No Telepon', 'trim|required|is_natural');
        $this->form_validation->set_rules('pin', 'PIN', 'required');
        $this->form_validation->set_rules('amount', 'Jumlah Transfer', 'required|integer');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );


        $id = $this->input->post('id');
        $pin = $this->input->post('pin');
        $phone = $this->input->post('phone');
        $amount = $this->input->post('amount');

        $from = $this->db->get_where('users', [
            'user_id' => $id,
            'pin' => $pin
        ])->row();

        if (is_null($from))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'PIN salah'
                ],
                REST_Controller::HTTP_OK
            );

        if ((int) $from->balance < (int) $amount)
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Balance tidak cukup'
                ],
                REST_Controller::HTTP_OK
            );

        $to = $this->db->get_where('users', [
            'phone' => $phone
        ])->row();

        if (is_null($to))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Rekening Tujuan tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        $from_new_amount = (int) $from->balance - (int) $amount;
        $to_new_amount = (int) $to->balance + (int) $amount;
        $this->db->update('users', ['balance' => (int) $from_new_amount], ['user_id' => $from->user_id]);
        $this->db->update('users', ['balance' => $to_new_amount], ['user_id' => $to->user_id]);


        $this->set_response(
            [
                'status' => true,
                'balance' => $from_new_amount
            ],
            REST_Controller::HTTP_OK
        );
    }

    public function merchandise_get()
    {
        $items = $this->db->get('merchandise')->result_array();

        $data = [];
        foreach ($items as $item)
        {
            $value = $item;
            $value['image_path'] = site_url('uploads/'. $item['image']);

            $data[] = $value;
        }

        $return = [
            'status' => true,
            'data' => $data
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function redeem_post()
    {
        $this->form_validation->set_rules('user_id', 'No Telepon', 'trim|required|integer');
        $this->form_validation->set_rules('merchandise_id', 'No Telepon', 'trim|required|integer');
        $this->form_validation->set_rules('pin', 'PIN', 'required');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );

        $user_id = $this->input->post('user_id');
        $merchandise_id = $this->input->post('merchandise_id');
        $pin = $this->input->post('pin');

        $user = $this->db->get_where('users', [
            'user_id' => $user_id,
            'pin' => $pin
        ])->row();

        if (is_null($user))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'PIN salah'
                ],
                REST_Controller::HTTP_OK);

        $merchandise = $this->db->get_where('merchandise', [
            'merchandise_id' => $merchandise_id
        ])->row();


        if (is_null($merchandise))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Merchandise tidak ditemukan'
                ],
                REST_Controller::HTTP_OK);

        if ( (int) $user->point < (int) $merchandise->point)
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'point tidak cukup'
                ],
                REST_Controller::HTTP_OK);


        $this->db->insert('redeems', [
            'user_id' => $user_id,
            'merchandise_id' => $merchandise_id,
            'status' => strtoupper('PENDING')
        ]);

        $new_point = $user->point - $merchandise->point;
        $this->db->update('users', ['point' => $new_point], ['user_id' => $user_id]);

        $return = [
            'status' => true,
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function redeems_get($id)
    {
        $items = $this->db->get_where('redeems', ['user_id' => $id])->result_array();

        $return = [
            'status' => true,
            'data' => $items
        ];

        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function change_balance_post(){
        $this->form_validation->set_rules('pin', 'PIN', 'required');
        $this->form_validation->set_rules('amount', 'Jumlah Transfer', 'required|integer');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );


        $id = $this->input->post('id');
        $pin = $this->input->post('pin');
        $amount = $this->input->post('amount');

        $user = $this->db->get_where('users', [
            'user_id' => $id,
            'pin' => $pin
        ])->row();

        if (is_null($user))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'PIN salah'
                ],
                REST_Controller::HTTP_OK
            );


        $new_amount = (int) $user->balance + (int) $amount;
        $this->db->update('users', ['balance' => (int) $new_amount], ['user_id' => $user->user_id]);


        $this->set_response(
            [
                'status' => true,
                'balance' => $new_amount
            ],
            REST_Controller::HTTP_OK
        );
    }
}