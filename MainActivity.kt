package com.emergency.trigger

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.os.Bundle
import android.widget.ArrayAdapter
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import com.emergency.trigger.api.SessionManager
import com.emergency.trigger.databinding.ActivityMainBinding
import com.emergency.trigger.service.TriggerService
import com.emergency.trigger.util.TriggerConfig
import com.google.android.material.textfield.TextInputEditText

class MainActivity : AppCompatActivity() {

    private lateinit var binding: ActivityMainBinding

    private val requiredPermissions = mutableListOf(
        Manifest.permission.RECORD_AUDIO
    ).apply {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            add(Manifest.permission.POST_NOTIFICATIONS)
        }
    }.toTypedArray()

    private val smsPermissions = arrayOf(
        Manifest.permission.SEND_SMS,
        Manifest.permission.ACCESS_FINE_LOCATION,
        Manifest.permission.ACCESS_COARSE_LOCATION
    )

    private val callPermissions = arrayOf(
        Manifest.permission.CALL_PHONE
    )

    private val permissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        if (permissions.entries.all { it.value }) {
            startTriggerService()
        } else {
            Toast.makeText(this, "Permissions required!", Toast.LENGTH_LONG).show()
        }
    }

    private val smsPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        if (permissions.entries.all { it.value }) {
            TriggerConfig.setSmsEnabled(this, true)
            binding.switchSms.isChecked = true
            showToast("SMS alerts enabled with location")
        } else {
            binding.switchSms.isChecked = false
            showToast("SMS permission denied")
        }
    }

    private val callPermissionLauncher = registerForActivityResult(
        ActivityResultContracts.RequestMultiplePermissions()
    ) { permissions ->
        if (permissions.entries.all { it.value }) {
            TriggerConfig.setCallEnabled(this, true)
            binding.switchCall.isChecked = true
            showToast("Emergency call enabled")
        } else {
            binding.switchCall.isChecked = false
            showToast("Call permission denied")
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        binding = ActivityMainBinding.inflate(layoutInflater)
        setContentView(binding.root)

        // Initialize API with saved token
        SessionManager.initApiClient(this)
        
        setupUI()
        checkPermissions()
    }

    private fun setupUI() {
        // ========== USER PROFILE ==========
        val userName = SessionManager.getUserName(this)
        binding.tvUserName.text = "Welcome, $userName"
        
        binding.btnLogout.setOnClickListener {
            SessionManager.clearSession(this)
            startActivity(Intent(this, LoginActivity::class.java))
            finish()
        }
        
        // ========== TRIGGERS ==========
        
        // Voice Trigger
        binding.switchVoice.isChecked = TriggerConfig.isVoiceEnabled(this)
        binding.switchVoice.setOnCheckedChangeListener { _, isChecked ->
            TriggerConfig.setVoiceEnabled(this, isChecked)
            restartService()
            showToast("Voice trigger ${if (isChecked) "ON" else "OFF"}")
        }

        // Power Button Trigger
        binding.switchPower.isChecked = TriggerConfig.isPowerEnabled(this)
        binding.switchPower.setOnCheckedChangeListener { _, isChecked ->
            TriggerConfig.setPowerEnabled(this, isChecked)
            restartService()
            showToast("Power button trigger ${if (isChecked) "ON" else "OFF"}")
        }

        // ========== EMERGENCY ACTIONS ==========
        
        // SMS Toggle
        binding.switchSms.isChecked = TriggerConfig.isSmsEnabled(this)
        binding.switchSms.setOnCheckedChangeListener { _, isChecked ->
            if (isChecked) {
                val notGranted = smsPermissions.filter {
                    ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
                }
                if (notGranted.isEmpty()) {
                    TriggerConfig.setSmsEnabled(this, true)
                } else {
                    smsPermissionLauncher.launch(notGranted.toTypedArray())
                }
            } else {
                TriggerConfig.setSmsEnabled(this, false)
                showToast("SMS alerts disabled")
            }
        }

        // Call Toggle
        binding.switchCall.isChecked = TriggerConfig.isCallEnabled(this)
        binding.switchCall.setOnCheckedChangeListener { _, isChecked ->
            if (isChecked) {
                val notGranted = callPermissions.filter {
                    ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
                }
                if (notGranted.isEmpty()) {
                    TriggerConfig.setCallEnabled(this, true)
                } else {
                    callPermissionLauncher.launch(notGranted.toTypedArray())
                }
            } else {
                TriggerConfig.setCallEnabled(this, false)
                showToast("Emergency call disabled")
            }
        }

        // Manage Contacts
        binding.btnManageContacts.setOnClickListener {
            showContactsDialog()
        }

        // Emergency Call Number
        binding.etCallNumber.setText(TriggerConfig.getEmergencyCallNumber(this))
        binding.btnSaveCallNumber.setOnClickListener {
            val number = binding.etCallNumber.text.toString().trim()
            TriggerConfig.setEmergencyCallNumber(this, number)
            showToast("Saved: $number")
        }

        // ========== WEBHOOK ==========
        
        binding.etWebhook.setText(TriggerConfig.getWebhookUrl(this))
        binding.btnSaveWebhook.setOnClickListener {
            val url = binding.etWebhook.text.toString()
            TriggerConfig.setWebhookUrl(this, url)
            showToast("Webhook URL saved")
        }

        // ========== TEST BUTTON ==========
        
        binding.btnTest.setOnClickListener {
            TriggerService.testTrigger(this)
            showToast("🚨 TEST TRIGGER SENT!")
        }

        updateUI()
    }

    private fun showContactsDialog() {
        val contacts = TriggerConfig.getEmergencyContacts(this).toMutableList()
        
        val dialogView = layoutInflater.inflate(R.layout.dialog_contacts, null)
        val listView = dialogView.findViewById<android.widget.ListView>(R.id.listContacts)
        val etNewContact = dialogView.findViewById<TextInputEditText>(R.id.etNewContact)
        val btnAdd = dialogView.findViewById<com.google.android.material.button.MaterialButton>(R.id.btnAddContact)
        
        val adapter = ArrayAdapter(this, android.R.layout.simple_list_item_1, contacts)
        listView.adapter = adapter
        
        listView.setOnItemLongClickListener { _, _, position, _ ->
            AlertDialog.Builder(this)
                .setTitle("Remove Contact")
                .setMessage("Remove ${contacts[position]}?")
                .setPositiveButton("Remove") { _, _ ->
                    TriggerConfig.removeEmergencyContact(this, contacts[position])
                    contacts.removeAt(position)
                    adapter.notifyDataSetChanged()
                    updateUI()
                }
                .setNegativeButton("Cancel", null)
                .show()
            true
        }
        
        val dialog = AlertDialog.Builder(this)
            .setTitle("📞 Emergency Contacts")
            .setView(dialogView)
            .setPositiveButton("Done", null)
            .create()
        
        btnAdd.setOnClickListener {
            val phone = etNewContact.text.toString().trim()
            if (phone.isNotBlank()) {
                TriggerConfig.addEmergencyContact(this, phone)
                contacts.add(phone)
                adapter.notifyDataSetChanged()
                etNewContact.text?.clear()
                updateUI()
            }
        }
        
        dialog.show()
    }

    private fun updateUI() {
        // Update contacts count
        val count = TriggerConfig.getEmergencyContacts(this).size
        binding.tvContactsCount.text = "$count contact${if (count != 1) "s" else ""}"
        
        // Update status
        binding.tvStatus.text = if (TriggerService.isRunning) "🟢 Active" else "🔴 Stopped"
    }

    private fun checkPermissions() {
        val notGranted = requiredPermissions.filter {
            ContextCompat.checkSelfPermission(this, it) != PackageManager.PERMISSION_GRANTED
        }

        if (notGranted.isEmpty()) {
            startTriggerService()
        } else {
            permissionLauncher.launch(notGranted.toTypedArray())
        }
    }

    private fun startTriggerService() {
        val intent = Intent(this, TriggerService::class.java)
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            startForegroundService(intent)
        } else {
            startService(intent)
        }
        updateUI()
    }

    private fun restartService() {
        if (TriggerService.isRunning) {
            startTriggerService()
        }
    }

    private fun showToast(message: String) {
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
        binding.tvLastEvent.text = message
    }

    override fun onResume() {
        super.onResume()
        updateUI()
    }
}
