<?php

require APPPATH . '/libraries/REST_Controller.php';

/**
 * Class Agent
 */
class Agent extends REST_Controller
{
    /**
     * Agent constructor.
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

        $data = $this->db->get_where('agents', [
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
        $user = $this->db->get_where('agents', ['agent_id' => $id])->row_array();
        $user['profile_path'] = site_url('uploads/'. $user['profile']);

        $return = [
            'status' => true,
            'data' => $user
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function pickup_lists_get($id)
    {
        $this->db->from('pickups p');
        $this->db->join('users u', 'u.user_id = p.user_id');
        $this->db->where('agent_id', $id);
        $items = $this->db->get()->result_array();

        $return = [
            'status' => true,
            'data' => $items
        ];

        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function deposit()
    {
        $id = $this->input->post('id');
        $amount = $this->input->post('amount');

        $agent = $this->db->get_where('agents', ['agent_id' => $id])->row_array();

        $balance = $amount + $agent['balance'];

        $this->db->update('agents', ['balance' => $balance], ['agent_id' => $id]);

        $return = [
            'status' => true,
            'balance' => $balance
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);

    }

    public function withdrawal_by_code_get($code)
    {

        $this->db->from('withdrawals w');
        $this->db->join('users u', 'u.user_id = w.user_id');
        $this->db->where('code', $code);
        $this->db->where('status', false);

        $data = $this->db->get()->row_array();

        if (is_null($data))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'data tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        $return = [
            'status' => true,
            'data' => $data
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function withdrawal_done_lists_get($id)
    {
        $this->db->from('withdrawals w');
        $this->db->join('users u', 'u.user_id = w.user_id');
        $this->db->where('w.agent_id', $id);
        $this->db->where('status', true);
        $data = $this->db->get()->result_array();
        $return = [
            'status' => true,
            'data' => $data
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function withdrawal_post()
    {
        $this->form_validation->set_rules('pin', 'PIN', 'required');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );


        $id = $this->input->post('id');
        $agent_id = $this->input->post('agent_id');
        $pin = $this->input->post('pin');

        $withdrawal = $this->db->get_where('withdrawals', [
            'withdrawal_id' => $id,
            'status' => false
        ])->row();

        if (is_null($withdrawal))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'data withdrawal tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        $user = $this->db->get_where('users', [
            'user_id' => $withdrawal->user_id,
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

        $agent = $this->db->get_where('agents', [
            'agent_id' => $agent_id
        ])->row();

        if ((int) $agent->balance < (int) $withdrawal->amount)
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Balance tidak cukup'
                ],
                REST_Controller::HTTP_OK
            );

        $commission = (int) $withdrawal->amount * 0.1 ;

        $this->db->update('withdrawals', ['status'=> true, 'commission' => $commission, 'agent_id' => $agent_id],
            ['withdrawal_id' => $id]);

        $user_new_amount = (int) $user->balance - (int) $withdrawal->amount;
        $agent_new_amount = (int) $agent->balance + $commission;
        $this->db->update('users', ['balance' => (int) $user_new_amount], ['user_id' => $user->user_id]);
        $this->db->update('agents', ['balance' => $agent_new_amount], ['agent_id' => $agent->agent_id]);

        $this->set_response(
            [
                'status' => true,
                'balance' => $agent_new_amount,
                'commission' => $commission
            ],
            REST_Controller::HTTP_OK
        );
    }

    public function collect_post()
    {
        $this->form_validation->set_rules('pin', 'PIN', 'required');
        $this->form_validation->set_rules('phone', 'No Telepon', 'trim|required|is_natural');
        $this->form_validation->set_rules('trash_id', 'Sampah', 'trim|required|integer');
        $this->form_validation->set_rules('agent_id', 'Agent', 'trim|required|integer');
        $this->form_validation->set_rules('weight', 'Agent', 'trim|required|numeric');

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
        $trash_id = $this->input->post('trash_id');
        $weight = $this->input->post('weight');
        $agent_id = $this->input->post('agent_id');

        $user = $this->db->get_where('users', [
            'phone' => $phone,
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

        $trash = $this->db->get_where('trash', [
            'trash_id' => $trash_id,
        ])->row();

        if (is_null($trash))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'data sampah tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        $price = (int) $trash->price;
        $weight = (float) $weight;
        $total = $price * $weight;

        $commission = 0.08 * $total;

        $point = floor($total/1000);

        $biaya = 0.02 * $total;

        $agent = $this->db->get_where('agents', [
            'agent_id' => $agent_id
        ])->row();

        if (is_null($user))
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Agent tidak ditemukan'
                ],
                REST_Controller::HTTP_OK
            );

        if ((int) $agent->balance < (int) $total)
            $this->set_response(
                [
                    'status' => false,
                    'message' => 'Balance tidak cukup'
                ],
                REST_Controller::HTTP_OK
            );
        $cost = (int) ($commission + $biaya);
        $transfer_balance = (int) $total - $cost;

        $user_new_amount = (int) $user->balance + $transfer_balance;

        $user_new_point = (int) $user->point + (int) $point;

        $agent_new_amount = (int) $agent->balance - ($total - $commission);
        $this->db->update('users', ['balance' => (int) $user_new_amount, 'point' => $user_new_point],
            ['user_id' => $user->user_id]);
        $this->db->update('agents', ['balance' => $agent_new_amount], ['agent_id' => $agent->agent_id]);

        $data = [
            'user_id' => $user->user_id,
            'agent_id' => $agent_id,
            'trash_id' => $trash_id,
            'price' => $price,
            'weight' => $weight,
            'total' => $total,
            'commission' => $commission,
            'point' => $point,
        ];

        $this->db->insert('collects', $data);


        $this->db->delete('pickups', array('user_id' => $user->user_id));

        $this->set_response(
            [
                'status' => true,
                'balance' => $agent_new_amount,
                'trash_price' => $price,
                'trash_weight' => $weight,
                'trash_value' => $total,
                'cost' => $cost,
                'transfered_balance' => $transfer_balance
            ],
            REST_Controller::HTTP_OK
        );
    }

    public function collect_history_get($id)
    {
        $this->db->from('collects c');
        $this->db->join('users u', 'u.user_id = c.user_id');
        $this->db->where('agent_id', $id);
        $items = $this->db->get()->result_array();

        $return = [
            'status' => true,
            'data' => $items
        ];

        $this->set_response($return, REST_Controller::HTTP_OK);
    }

    public function user_by_phone_post()
    {

        $this->form_validation->set_rules('phone', 'No Telepon', 'trim|required|is_natural');

        if ($this->form_validation->run() == FALSE)
            $this->set_response(
                [
                    'status' => false,
                    'message' => validation_errors()
                ],
                REST_Controller::HTTP_BAD_REQUEST
            );

        $user = $this->db->get_where('users', ['phone' => $this->input->post('phone')])->row_array();
        $user['profile_path'] = site_url('uploads/'. $user['profile']);

        $return = [
            'status' => true,
            'data' => $user
        ];
        $this->set_response($return, REST_Controller::HTTP_OK);
    }


}