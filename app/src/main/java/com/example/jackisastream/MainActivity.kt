package com.example.jackisastream

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import com.example.jackisastream.ui.MainApp
import com.example.jackisastream.ui.theme.JackisaStreamTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        setContent {
            JackisaStreamTheme {
                MainApp()
            }
        }
    }
}
